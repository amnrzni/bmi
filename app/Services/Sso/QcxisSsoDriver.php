<?php

namespace App\Services\Sso;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * QCXIS single sign-on — OpenID Connect, Authorization Code + PKCE.
 *
 * Hand-rolled rather than via Socialite for one reason: QCXIS requires PKCE
 * S256 on *every* client including confidential ones, and Socialite setups
 * default PKCE off — a mismatch that fails at the authorize step with an error
 * that doesn't point at the cause.
 *
 * We only ever need to answer "who is this", once. So:
 *   - no `offline_access`, no refresh tokens, nothing long-lived to store;
 *     Laravel's own session takes over immediately after sign-in
 *   - the identity comes from /userinfo rather than by verifying the id_token
 *     against the JWKS. Both the token response and userinfo arrive over direct
 *     server-to-server TLS from the issuer, which OIDC accepts in place of
 *     signature checking for the authorization-code flow (OIDC Core §3.1.3.7).
 *     It also keeps a JWT library and JWKS cache out of the project entirely.
 *
 * Access tokens here are opaque strings, never JWTs — nothing decodes them.
 */
class QcxisSsoDriver implements SsoDriver
{
    private const STATE_KEY = 'sso.qcxis.state';

    private const VERIFIER_KEY = 'sso.qcxis.verifier';

    /** Documented paths, used when the discovery document can't be fetched. */
    private const FALLBACK_ENDPOINTS = [
        'authorization_endpoint' => '/v1/oauth/authorize',
        'token_endpoint' => '/v1/oauth/token',
        'userinfo_endpoint' => '/v1/oauth/userinfo',
    ];

    public function redirect(): RedirectResponse
    {
        // PKCE: verifier is 43 URL-safe chars; challenge is BASE64URL(SHA256(verifier)).
        $verifier = $this->base64url(random_bytes(32));
        $state = $this->base64url(random_bytes(16));

        // Single-use, and scoped to this browser session — this is what stops a
        // forged callback from signing someone in.
        session([self::STATE_KEY => $state, self::VERIFIER_KEY => $verifier]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->config('scopes'),
            'state' => $state,
            'code_challenge' => $this->base64url(hash('sha256', $verifier, binary: true)),
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($this->endpoint('authorization_endpoint').'?'.$query);
    }

    public function emailFromCallback(Request $request): ?string
    {
        $expectedState = $request->session()->pull(self::STATE_KEY);
        $verifier = $request->session()->pull(self::VERIFIER_KEY);

        // The user declined consent, or the provider bounced us back.
        if ($request->filled('error')) {
            return null;
        }

        // Constant-time comparison, and a missing session value must never pass.
        if (! $expectedState || ! $verifier || ! hash_equals($expectedState, (string) $request->query('state'))) {
            Log::warning('QCXIS SSO: state mismatch on callback.');

            return null;
        }

        if (! $request->filled('code')) {
            return null;
        }

        $accessToken = $this->exchangeCode($request->string('code')->value(), $verifier);

        return $accessToken ? $this->emailFromUserinfo($accessToken) : null;
    }

    /** The authorization code is single-use and lives ~60 seconds — redeem at once. */
    private function exchangeCode(string $code, string $verifier): ?string
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            // Must be byte-identical to the value sent to /authorize.
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
        ];

        $request = Http::asForm()->timeout(10);

        if ($this->isConfidential()) {
            // Confidential client: HTTP Basic (client_secret_basic).
            $request = $request->withBasicAuth($this->config('client_id'), $this->config('client_secret'));
        } else {
            // Public client: no secret exists, so client_id goes in the body and
            // PKCE is the only proof that this is the app that started the flow.
            $payload['client_id'] = $this->config('client_id');
        }

        $response = $request->post($this->endpoint('token_endpoint'), $payload);

        if ($response->failed()) {
            Log::warning('QCXIS SSO: token exchange failed.', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    private function emailFromUserinfo(string $accessToken): ?string
    {
        // GET only — the endpoint answers 405 to POST.
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get($this->endpoint('userinfo_endpoint'));

        if ($response->failed()) {
            Log::warning('QCXIS SSO: userinfo failed.', ['status' => $response->status()]);

            return null;
        }

        $email = $response->json('email');

        if (! $email) {
            Log::warning('QCXIS SSO: no email claim returned; check the email scope is granted.');

            return null;
        }

        // An unverified address must not match a roster entry: it would let
        // someone sign in as a colleague by claiming their address.
        //
        // Compared loosely on purpose. The claim is a boolean in the OIDC spec,
        // but providers commonly send "true" or 1, and a strict !== true would
        // reject a genuinely verified account.
        $rawVerified = $response->json('email_verified');
        $isVerified = filter_var($rawVerified, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;

        if (config('sso.qcxis.require_verified_email') && ! $isVerified) {
            Log::warning('QCXIS SSO: refused an unverified email address.', [
                'email' => $email,
                // Logged with its type so a format mismatch is obvious rather
                // than looking like a genuinely unverified account.
                'email_verified_raw' => var_export($rawVerified, true),
                'claims_returned' => array_keys($response->json() ?? []),
            ]);

            return null;
        }

        return $email;
    }

    /**
     * A secret is NOT required: QCXIS apps registered as "Public" are issued
     * none, and authenticate at the token endpoint with client_id + PKCE.
     */
    public function isAvailable(): bool
    {
        return filled($this->config('client_id')) && filled($this->redirectUri());
    }

    /** Registered as Confidential (has a secret) rather than Public. */
    public function isConfidential(): bool
    {
        return filled($this->config('client_secret'));
    }

    public function label(): string
    {
        return 'Sign in with QCXIS';
    }

    /**
     * Selected as the driver but missing credentials — surfaced on the login
     * page during setup so the button's absence isn't a silent mystery.
     */
    public function isMisconfigured(): bool
    {
        return ! $this->isAvailable();
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Endpoints come from the discovery document, cached for a day, and fall
     * back to the documented paths if it can't be reached — a discovery blip
     * shouldn't take sign-in down.
     */
    private function endpoint(string $name): string
    {
        $issuer = rtrim((string) $this->config('issuer'), '/');

        $discovered = Cache::remember(
            'sso.qcxis.discovery.'.md5($issuer),
            now()->addDay(),
            function () use ($issuer) {
                try {
                    $response = Http::timeout(5)->get($issuer.'/.well-known/openid-configuration');

                    return $response->successful() ? $response->json() : [];
                } catch (\Throwable $e) {
                    Log::warning('QCXIS SSO: discovery unavailable.', ['message' => $e->getMessage()]);

                    return [];
                }
            },
        );

        return $discovered[$name] ?? $issuer.self::FALLBACK_ENDPOINTS[$name];
    }

    /**
     * QCXIS matches redirect URIs by exact string — no wildcards, no
     * trailing-slash forgiveness — so this must equal the registered value
     * character for character.
     */
    private function redirectUri(): string
    {
        return $this->config('redirect_uri') ?: route('sso.callback');
    }

    private function config(string $key): ?string
    {
        return config("sso.qcxis.{$key}");
    }

    private function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

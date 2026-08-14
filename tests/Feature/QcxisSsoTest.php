<?php

use App\Models\User;
use App\Services\Sso\QcxisSsoDriver;
use App\Services\Sso\SsoDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The QCXIS OIDC driver.
 *
 * Everything here mirrors a rule the provider documents as stricter than a
 * lenient IdP: PKCE S256 is mandatory even for confidential clients, `state`
 * is required, redirect URIs match by exact string, and access tokens are
 * opaque so nothing may decode them.
 */
beforeEach(function () {
    config()->set('sso.driver', 'qcxis');
    config()->set('sso.qcxis', [
        'issuer' => 'https://qcxis.com',
        'client_id' => 'c_test123',
        'client_secret' => 'secret123',
        'redirect_uri' => 'https://bmi.example.test/auth/sso/callback',
        'scopes' => 'openid profile email',
        'require_verified_email' => true,
    ]);

    Cache::flush();

    // Discovery is faked everywhere so no test reaches the network.
    Http::preventStrayRequests();
    Http::fake([
        'qcxis.com/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://qcxis.com/v1/oauth/authorize',
            'token_endpoint' => 'https://qcxis.com/v1/oauth/token',
            'userinfo_endpoint' => 'https://qcxis.com/v1/oauth/userinfo',
        ]),
    ]);

    $this->driver = new QcxisSsoDriver;
});

function fakeTokenAndUserinfo(array $userinfo, int $tokenStatus = 200): void
{
    Http::fake([
        'qcxis.com/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://qcxis.com/v1/oauth/authorize',
            'token_endpoint' => 'https://qcxis.com/v1/oauth/token',
            'userinfo_endpoint' => 'https://qcxis.com/v1/oauth/userinfo',
        ]),
        'qcxis.com/v1/oauth/token' => Http::response(
            $tokenStatus === 200
                ? ['access_token' => 'opaque-token', 'token_type' => 'Bearer', 'expires_in' => 900]
                : ['error' => 'invalid_grant'],
            $tokenStatus,
        ),
        'qcxis.com/v1/oauth/userinfo' => Http::response($userinfo),
    ]);
}

/** Drive a full callback with the state/verifier the driver stored. */
function callbackWith(array $query): ?string
{
    $driver = new QcxisSsoDriver;
    $driver->redirect(); // seeds state + verifier into the session

    $request = Request::create('/auth/sso/callback', 'GET', $query);
    $request->setLaravelSession(app('session.store'));

    return $driver->emailFromCallback($request);
}

// ------------------------------------------------------------------ authorize

it('sends the user to authorize with pkce s256 and state', function () {
    $url = $this->driver->redirect()->getTargetUrl();
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://qcxis.com/v1/oauth/authorize')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe('c_test123')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->not->toBeEmpty()
        ->and($query['state'])->not->toBeEmpty()
        // Exact string match at the provider — no trailing-slash forgiveness.
        ->and($query['redirect_uri'])->toBe('https://bmi.example.test/auth/sso/callback');
});

it('derives the code challenge as base64url sha256 of the verifier', function () {
    $url = $this->driver->redirect()->getTargetUrl();
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $verifier = session('sso.qcxis.verifier');
    $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    expect($query['code_challenge'])->toBe($expected)
        // 43 chars of URL-safe randomness, inside the 43–128 range.
        ->and(strlen($verifier))->toBe(43)
        ->and($verifier)->toMatch('/^[A-Za-z0-9\-_]+$/');
});

it('keeps state url-safe so it survives being echoed back', function () {
    $this->driver->redirect();

    // The provider echoes state into the redirect without re-encoding, so an
    // unsafe character would corrupt the callback URL.
    expect(session('sso.qcxis.state'))->toMatch('/^[A-Za-z0-9\-_]+$/');
});

it('does not request offline_access', function () {
    $url = $this->driver->redirect()->getTargetUrl();
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    // We mint our own session; a refresh token would be a long-lived
    // credential with nothing to do.
    expect($query['scope'])->toBe('openid profile email')
        ->and($query['scope'])->not->toContain('offline_access');
});

// ------------------------------------------------------------------- callback

it('exchanges the code and returns the verified email', function () {
    fakeTokenAndUserinfo(['sub' => 'u-1', 'email' => 'along@qcxis.com', 'email_verified' => true]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();

    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'authcode',
        'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    expect($driver->emailFromCallback($request))->toBe('along@qcxis.com');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/v1/oauth/token')) {
            return true;
        }

        return $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'authcode'
            && filled($request['code_verifier'])
            && $request['redirect_uri'] === 'https://bmi.example.test/auth/sso/callback'
            // Confidential client authenticates with HTTP Basic.
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ');
    });
});

it('rejects a callback whose state does not match the session', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    // A forged callback is the attack this parameter exists to stop.
    expect(callbackWith(['code' => 'authcode', 'state' => 'not-the-stored-state']))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/oauth/token'));
});

it('rejects a callback with no state at all', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    expect(callbackWith(['code' => 'authcode']))->toBeNull();
});

it('treats a declined consent as a cancelled sign-in', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    // access_denied is the only error the provider delivers via redirect.
    expect(callbackWith(['error' => 'access_denied', 'state' => 'whatever']))->toBeNull();
});

it('consumes state so a callback cannot be replayed', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $state = session('sso.qcxis.state');

    $make = function () use ($state) {
        $request = Request::create('/auth/sso/callback', 'GET', [
            'code' => 'authcode', 'state' => $state,
        ]);
        $request->setLaravelSession(app('session.store'));

        return $request;
    };

    expect($driver->emailFromCallback($make()))->toBe('along@qcxis.com')
        // Second use finds no state in the session.
        ->and($driver->emailFromCallback($make()))->toBeNull();
});

it('returns nothing when the token exchange fails', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com'], tokenStatus: 400);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'expired', 'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    expect($driver->emailFromCallback($request))->toBeNull();
});

it('refuses an unverified email address', function () {
    fakeTokenAndUserinfo(['sub' => 'u-2', 'email' => 'along@qcxis.com', 'email_verified' => false]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'authcode', 'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    // Accepting it would let someone sign in as a colleague by claiming
    // their address — the roster match is on email alone.
    expect($driver->emailFromCallback($request))->toBeNull();
});

it('can be configured to accept unverified emails', function () {
    config()->set('sso.qcxis.require_verified_email', false);
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => false]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'authcode', 'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    expect($driver->emailFromCallback($request))->toBe('along@qcxis.com');
});

// -------------------------------------------------------------- configuration

it('is unavailable until the client credentials are set', function () {
    config()->set('sso.qcxis.client_id', null);

    expect((new QcxisSsoDriver)->isAvailable())->toBeFalse();
});

it('falls back to the documented endpoints when discovery is unreachable', function () {
    Http::fake([
        'qcxis.com/.well-known/openid-configuration' => Http::response('', 503),
    ]);

    $url = (new QcxisSsoDriver)->redirect()->getTargetUrl();

    expect($url)->toStartWith('https://qcxis.com/v1/oauth/authorize');
});

it('resolves the qcxis driver from config', function () {
    expect(app(SsoDriver::class))->toBeInstanceOf(QcxisSsoDriver::class);
});

// ------------------------------------------------------- roster exposure

it('never exposes the staff picker when a real driver is active', function () {
    // This page lists every staff name and email. Guarding it on the ACTIVE
    // driver would publish the whole roster the moment QCXIS was configured.
    User::factory()->create(['name' => 'Along', 'email' => 'along@qcxis.com']);

    $this->get(route('sso.stub'))->assertNotFound();
});

// ------------------------------------------------------------- end to end

it('signs in a roster member through the full callback route', function () {
    $staff = User::factory()->create(['email' => 'along@qcxis.com']);
    fakeTokenAndUserinfo(['sub' => 'u-1', 'email' => 'along@qcxis.com', 'email_verified' => true]);

    // Prime state the way the redirect would.
    (new QcxisSsoDriver)->redirect();

    $this->get(route('sso.callback', ['code' => 'authcode', 'state' => session('sso.qcxis.state')]))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($staff);
});

it('rejects a valid qcxis identity that is not on the roster', function () {
    fakeTokenAndUserinfo(['sub' => 'u-9', 'email' => 'stranger@qcxis.com', 'email_verified' => true]);

    (new QcxisSsoDriver)->redirect();

    $this->get(route('sso.callback', ['code' => 'authcode', 'state' => session('sso.qcxis.state')]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

// ------------------------------------------------------------- login page

it('shows a setup hint when qcxis is selected but not configured', function () {
    config()->set('sso.qcxis.client_id', null);
    config()->set('app.debug', true);

    // Without this the button simply vanishes and whoever is wiring SSO up has
    // nothing to go on.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee("QCXIS SSO isn't configured", false)
        ->assertSee('QCXIS_CLIENT_ID');
});

it('hides the setup hint from real users when debug is off', function () {
    config()->set('sso.qcxis.client_id', null);
    config()->set('app.debug', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('QCXIS_CLIENT_ID')
        ->assertSee("Sign-in isn't available yet", false);
});

it('shows only the qcxis button once configured', function () {
    // The email/password form was removed from the page by request; the button
    // is the only thing on it now.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in with QCXIS')
        ->assertDontSee('Password')
        ->assertDontSee('name="password"', false);
});

it('keeps the admin fallback login working even though the form is hidden', function () {
    // The POST route survives so an SSO outage can't lock every admin out.
    $admin = User::factory()->admin()->create(['email' => 'boss@qcxis.com']);

    $this->post(route('login'), ['email' => 'boss@qcxis.com', 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($admin);
});

// ------------------------------------------------------- public client mode

it('is available without a secret, as a public app has none', function () {
    config()->set('sso.qcxis.client_secret', null);

    $driver = new QcxisSsoDriver;

    expect($driver->isAvailable())->toBeTrue()
        ->and($driver->isConfidential())->toBeFalse();
});

it('authenticates a public client with client_id in the body, not basic auth', function () {
    config()->set('sso.qcxis.client_secret', null);
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'authcode', 'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    expect($driver->emailFromCallback($request))->toBe('along@qcxis.com');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/v1/oauth/token')) {
            return true;
        }

        // PKCE is the only proof for a public client, so the verifier must
        // still be present — the secret simply doesn't exist.
        return $request['client_id'] === 'c_test123'
            && filled($request['code_verifier'])
            && empty($request->header('Authorization'));
    });
});

it('still uses basic auth when a secret is configured', function () {
    fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => true]);

    $driver = new QcxisSsoDriver;
    $driver->redirect();
    $request = Request::create('/auth/sso/callback', 'GET', [
        'code' => 'authcode', 'state' => session('sso.qcxis.state'),
    ]);
    $request->setLaravelSession(app('session.store'));

    $driver->emailFromCallback($request);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/v1/oauth/token')) {
            return true;
        }

        return str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ')
            && empty($request['client_id']);
    });
});

it('accepts the verified claim however the provider formats it', function () {
    // Boolean per the spec, but "true"/1 are common in the wild and a strict
    // comparison would reject a genuinely verified account.
    foreach ([true, 'true', 1, '1'] as $value) {
        fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => $value]);

        $driver = new QcxisSsoDriver;
        $driver->redirect();
        $request = Request::create('/auth/sso/callback', 'GET', [
            'code' => 'authcode', 'state' => session('sso.qcxis.state'),
        ]);
        $request->setLaravelSession(app('session.store'));

        expect($driver->emailFromCallback($request))
            ->toBe('along@qcxis.com', 'failed for '.var_export($value, true));
    }
});

it('still refuses the falsey forms', function () {
    foreach ([false, 'false', 0, '0', null] as $value) {
        fakeTokenAndUserinfo(['email' => 'along@qcxis.com', 'email_verified' => $value]);

        $driver = new QcxisSsoDriver;
        $driver->redirect();
        $request = Request::create('/auth/sso/callback', 'GET', [
            'code' => 'authcode', 'state' => session('sso.qcxis.state'),
        ]);
        $request->setLaravelSession(app('session.store'));

        expect($driver->emailFromCallback($request))
            ->toBeNull('should have refused '.var_export($value, true));
    }
});

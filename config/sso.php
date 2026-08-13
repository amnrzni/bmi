<?php

/**
 * Single sign-on.
 *
 * `qcxis` is the real driver (OpenID Connect, Authorization Code + PKCE).
 * `stub` is a development picker that refuses to run outside local/testing.
 */
return [

    'driver' => env('SSO_DRIVER', 'stub'),

    'stub' => [
        'enabled' => env('SSO_STUB_ENABLED', true),
    ],

    'qcxis' => [
        'issuer' => env('QCXIS_ISSUER', 'https://qcxis.com'),

        // From Console → Settings → SSO apps.
        'client_id' => env('QCXIS_CLIENT_ID'),

        /**
         * Confidential apps only. Apps registered as "Public" are issued no
         * secret and authenticate with client_id + PKCE instead — leave this
         * unset for those. The secret is shown ONCE at creation; if it's lost
         * the app has to be deleted and recreated.
         */
        'client_secret' => env('QCXIS_CLIENT_SECRET'),

        /**
         * Must match a registered redirect URI by exact string comparison —
         * scheme, host, port, path and trailing slash all count.
         * Defaults to route('sso.callback'), which relies on APP_URL being right.
         */
        'redirect_uri' => env('QCXIS_REDIRECT_URI'),

        // No offline_access: we mint a Laravel session at sign-in and never
        // need a refresh token, so there's nothing long-lived to store or leak.
        'scopes' => env('QCXIS_SCOPES', 'openid profile email'),

        /**
         * Refuse an unverified email address. On by default: the roster match is
         * purely on email, so accepting an unverified one would let somebody
         * sign in as a colleague by claiming their address.
         *
         * Turn off only if QCXIS accounts legitimately have unverified emails
         * and staff are locked out as a result.
         */
        'require_verified_email' => env('QCXIS_REQUIRE_VERIFIED_EMAIL', true),
    ],

];

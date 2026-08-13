<?php

namespace App\Services\Sso;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Development stand-in for QCXIS SSO: presents a picker of roster emails so the
 * staff-facing screens can be built and tested before the real IdP exists.
 *
 * Refuses to work outside local/testing. This is the one place where a
 * misconfiguration would let anyone sign in as anyone, so the guard is on the
 * environment itself rather than on config alone.
 */
class StubSsoDriver implements SsoDriver
{
    public function redirect(): RedirectResponse
    {
        return redirect()->route('sso.stub');
    }

    public function emailFromCallback(Request $request): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $email = $request->string('email')->trim()->value();

        return $email !== '' ? $email : null;
    }

    public function isAvailable(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('sso.stub.enabled');
    }

    public function label(): string
    {
        return 'Sign in (development)';
    }
}

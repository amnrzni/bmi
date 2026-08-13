<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Services\Sso\SsoDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Local email/password login is an ADMIN-ONLY fallback, so the challenge stays
 * runnable if SSO breaks. Staff always authenticate through SSO.
 */
class LoginController
{
    public function show(SsoDriver $driver)
    {
        return view('auth.login', [
            'ssoAvailable' => $driver->isAvailable(),
            'ssoLabel' => $driver->label(),
            // Only while debugging: tells whoever is setting SSO up why the
            // button is missing, instead of leaving them guessing.
            'ssoSetupHint' => config('app.debug')
                && config('sso.driver') === 'qcxis'
                && ! $driver->isAvailable(),
        ]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Scoped to admins with a password set: a staff row has no password, so
        // this can never become a back door around SSO.
        $attempted = Auth::attempt([
            ...$credentials,
            'role' => Role::Admin->value,
        ], $request->boolean('remember'));

        if (! $attempted) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match an admin account.',
            ]);
        }

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

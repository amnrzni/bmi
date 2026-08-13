<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Sso\SsoDriver;
use App\Services\Sso\StubSsoDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Identity matching lives here rather than in the driver, so the rules hold
 * whichever driver is in use.
 */
class SsoController
{
    public function __construct(private readonly SsoDriver $driver) {}

    public function redirect()
    {
        abort_unless($this->driver->isAvailable(), 503, 'Single sign-on is not available.');

        return $this->driver->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless($this->driver->isAvailable(), 503, 'Single sign-on is not available.');

        $email = $this->driver->emailFromCallback($request);

        if ($email === null) {
            return redirect()->route('login')
                ->withErrors(['sso' => 'Sign-in was cancelled or could not be verified.']);
        }

        // Email match against the roster. No match is a rejection, never an
        // auto-created account — the master roster is the source of truth.
        $user = User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'sso' => "The account {$email} isn't on the challenge roster. Ask an admin to add you.",
            ]);
        }

        if ($user->left_at !== null && $user->left_at->isPast()) {
            return redirect()->route('login')->withErrors([
                'sso' => 'This account has left the challenge.',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Powers the "never signed in" flag on the roster.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('home'));
    }

    /** Dev-only picker standing in for the real IdP screen. */
    public function stub()
    {
        // Guarded on the STUB driver specifically, not on whichever driver is
        // active. This page lists every staff name and email, so tying it to
        // `$this->driver->isAvailable()` would expose the whole roster the
        // moment a real driver was configured.
        abort_unless(
            $this->driver instanceof StubSsoDriver && $this->driver->isAvailable(),
            404,
        );

        return view('auth.sso-stub', [
            'users' => User::with('team')->orderByDesc('role')->orderBy('name')->get(),
        ]);
    }
}

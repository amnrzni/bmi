<?php

namespace App\Livewire\Merdeka;

use App\Models\MerdekaJudge;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The contest's sign-in: type an email, and if it's on the panel you're in.
 *
 * No password and no SSO. That means the email is the entire credential —
 * anyone who knows a judge's address can score as them. Accepted deliberately
 * for a two-person panel judging office decorations, and the reason the throttle
 * below exists: without it the door can be walked, one address at a time.
 */
#[Layout('components.layouts.merdeka', ['heading' => 'Log Masuk'])]
#[Title('Log Masuk — Sudut Kemerdekaan 2026')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public function mount(): void
    {
        if (MerdekaJudge::current()) {
            $this->redirectRoute('merdeka.judging');
        }
    }

    public function submit(): void
    {
        // Trimmed before validating: a pasted or phone-typed address arrives
        // with a trailing space, and `email` would reject it as malformed.
        $this->email = trim($this->email);

        $this->validate();

        $key = 'merdeka-login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            $this->addError('email', 'Terlalu banyak percubaan. Sila cuba lagi dalam '
                .ceil(RateLimiter::availableIn($key) / 60).' minit.');

            return;
        }

        $judge = MerdekaJudge::findByEmail($this->email);

        if (! $judge) {
            RateLimiter::hit($key, decaySeconds: 900);

            $this->addError('email', 'E-mel ini tiada dalam senarai panel hakim.');

            return;
        }

        RateLimiter::clear($key);

        // A new session id on privilege change, exactly as a real sign-in does.
        session()->regenerate();
        session([MerdekaJudge::SESSION_KEY => $judge->id]);

        $this->redirectRoute('merdeka.judging');
    }

    public function render()
    {
        return view('livewire.merdeka.login');
    }
}

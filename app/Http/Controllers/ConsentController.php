<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ConsentController
{
    public function show(Request $request)
    {
        if (! $request->user()->is_participant || $request->user()->hasConsented()) {
            return redirect()->route('home');
        }

        return view('auth.consent');
    }

    public function store(Request $request)
    {
        $request->validate([
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'You need to accept to take part.',
        ]);

        // Timestamped so there's a record of when consent was given.
        $request->user()->update(['consented_at' => now()]);

        return redirect()->route('home');
    }

    /** Withdrawing keeps the team aggregate honest but drops the person's identity. */
    public function withdraw(Request $request)
    {
        $user = $request->user();

        $user->update([
            'anonymised_at' => now(),
            'is_participant' => false,
            'left_at' => now()->toDateString(),
        ]);

        return redirect()->route('logout');
    }
}

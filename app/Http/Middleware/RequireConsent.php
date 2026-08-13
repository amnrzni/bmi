<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Participation is opt-in, and the visibility of body weight to the organising
 * group has to be stated up front (HANDOFF.md §8). Nobody reaches any screen
 * before accepting that.
 *
 * Non-participants (admins who only run the challenge) have nothing to consent
 * to and pass straight through.
 */
class RequireConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_participant && ! $user->hasConsented()) {
            return redirect()->route('consent.show');
        }

        return $next($request);
    }
}

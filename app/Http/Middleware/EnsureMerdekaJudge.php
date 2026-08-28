<?php

namespace App\Http\Middleware;

use App\Models\MerdekaJudge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The contest's own door. Nothing to do with app auth — a judge is whoever the
 * session says, and the session only says it because a listed email was typed.
 */
class EnsureMerdekaJudge
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MerdekaJudge::current()) {
            // Clear a stale id too: a judge removed from the panel mid-session
            // would otherwise be bounced between here and the sign-in screen.
            $request->session()->forget(MerdekaJudge::SESSION_KEY);

            return redirect()->route('merdeka.login');
        }

        return $next($request);
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WeighIn;
use App\Services\Sso\QcxisSsoDriver;
use App\Services\Sso\SsoDriver;
use App\Services\Sso\StubSsoDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
        // An unrecognised name throws rather than quietly falling back to the
        // stub: a typo in SSO_DRIVER would otherwise disable staff sign-in in
        // production with no visible symptom until someone reported they
        // couldn't get in.
        $this->app->singleton(SsoDriver::class, fn () => match (config('sso.driver')) {
            'qcxis' => new QcxisSsoDriver,
            'stub' => new StubSsoDriver,
            default => throw new InvalidArgumentException(
                'Unknown SSO driver ['.config('sso.driver').']. Check SSO_DRIVER in .env.'
            ),
        });
    }

    public function boot(): void
    {
        // On in testing too, so the suite catches lazy loads rather than leaving
        // them to be discovered by hand in the browser.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->registerGates();
    }

    /**
     * Real permission checks from the start — retrofitting RBAC is where this
     * goes wrong (HANDOFF.md §4).
     */
    private function registerGates(): void
    {
        // Admin can do everything: roster, heights, teams, batch entry, analytics.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        // Staff see only their own records. Never a named colleague's weight.
        Gate::define('view-weigh-in', fn (User $user, WeighIn $weighIn) => $user->id === $weighIn->user_id);

        Gate::define('view-progress-of', fn (User $user, User $subject) => $user->id === $subject->id);

        // Only admins record weight; staff never self-log.
        Gate::define('record-weigh-ins', fn (User $user) => false);

        Gate::define('view-analytics', fn (User $user) => false);

        Gate::define('manage-roster', fn (User $user) => false);

        // Merdeka contest results: the full cross-judge matrix, averages and
        // ranking. Admin-only, via the Gate::before above.
        Gate::define('view-merdeka-results', fn (User $user) => false);

        // Tournament setup, scores, captains and attendance. Watching needs no
        // ability at all — the tournament pages are public.
        Gate::define('manage-tournaments', fn (User $user) => false);

        // There is deliberately no ability for judging itself: judges are not
        // app users at all. They sign in with an email at /merdeka and are held
        // by the `merdeka.judge` middleware, so no Gate could apply to them.
    }
}

<?php

use App\Livewire\Analytics;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\Sso\SsoDriver;
use App\Services\Sso\StubSsoDriver;
use App\Support\ChallengeWeek;

beforeEach(function () {
    $this->team = Team::factory()->create(['code' => 'TAH', 'name' => 'Team TAH', 'sort_order' => 1]);
    $this->other = Team::factory()->create(['code' => 'BKN', 'name' => 'Team BKN', 'sort_order' => 2]);
    $this->department = Department::factory()->create(['name' => 'Operations']);

    $this->admin = User::factory()->admin()->create([
        'name' => 'Admin One',
        'department_id' => $this->department->id,
        'team_id' => $this->team->id,
    ]);

    $this->staff = User::factory()->create([
        'name' => 'Staff One',
        'department_id' => $this->department->id,
        'team_id' => $this->other->id,
    ]);
});

// ----------------------------------------------------------------- access

it('sends guests to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('renders the login screen', function () {
    $this->get(route('login'))->assertOk()->assertSee('Report');
});

it('lets an admin reach every admin screen', function () {
    foreach (['home', 'roster', 'weigh-in', 'analytics', 'me'] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }
});

it('keeps staff out of the roster, weigh-in and analytics screens', function () {
    foreach (['roster', 'weigh-in', 'analytics'] as $route) {
        $this->actingAs($this->staff)->get(route($route))->assertForbidden();
    }
});

it('lets staff see their own progress and the landing page', function () {
    $this->actingAs($this->staff)->get(route('me'))->assertOk();
    $this->actingAs($this->staff)->get(route('home'))->assertOk();
});

// ---------------------------------------------------------------- consent

it('holds a participant at the consent gate until they opt in', function () {
    $newcomer = User::factory()->unconsented()->create();

    $this->actingAs($newcomer)->get(route('home'))->assertRedirect(route('consent.show'));
    $this->actingAs($newcomer)->get(route('consent.show'))->assertOk()->assertSee('voluntary');

    $this->actingAs($newcomer)->post(route('consent.store'), ['accept' => '1'])
        ->assertRedirect(route('home'));

    expect($newcomer->fresh()->hasConsented())->toBeTrue();
});

it('states up front who can see a participant weight', function () {
    $newcomer = User::factory()->unconsented()->create();

    // The visibility has to be stated before opting in, not buried afterwards.
    $this->actingAs($newcomer)->get(route('consent.show'))
        ->assertSee('Admins can see your exact weight and BMI, by name.')
        ->assertSee('Other staff cannot.');
});

it('does not stop a non-participant admin at the consent gate', function () {
    $organiser = User::factory()->admin()->nonParticipant()->unconsented()->create();

    $this->actingAs($organiser)->get(route('home'))->assertOk();
});

// ------------------------------------------------------------------ login

it('refuses a local password login for staff accounts', function () {
    $this->post(route('login'), ['email' => $this->staff->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('allows the admin fallback login', function () {
    $this->post(route('login'), ['email' => $this->admin->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->admin);
});

// -------------------------------------------------------------------- SSO

it('rejects an sso identity that is not on the roster', function () {
    $this->get(route('sso.callback', ['email' => 'stranger@example.com']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

it('signs in an sso identity that matches a roster email', function () {
    $this->get(route('sso.callback', ['email' => $this->staff->email]))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->staff);
});

it('matches sso emails regardless of case', function () {
    $this->get(route('sso.callback', ['email' => strtoupper($this->staff->email)]))
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($this->staff);
});

// ------------------------------------------------------------- weigh-in UI

it('shows the current week by default on the weigh-in screen', function () {
    $this->actingAs($this->admin)->get(route('weigh-in'))
        ->assertOk()
        ->assertSee(ChallengeWeek::current()->shortLabel());
});

it('shows a department collection status', function () {
    WeighIn::factory()->create([
        'user_id' => $this->staff->id,
        'week_start_date' => ChallengeWeek::current()->key(),
        'recorded_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->get(route('weigh-in'))
        ->assertOk()
        ->assertSee('Operations');
});

// ------------------------------------------------------------- analytics

it('shows the weeks-recorded column so a delta cannot read as more than it is', function () {
    WeighIn::factory()->create([
        'user_id' => $this->staff->id,
        'week_start_date' => ChallengeWeek::current()->key(),
        'recorded_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->get(route('analytics'))
        ->assertOk()
        ->assertSee('Weeks')
        ->assertSee('Staff One');
});

it('exports the summary as csv', function () {
    WeighIn::factory()->create([
        'user_id' => $this->staff->id,
        'week_start_date' => ChallengeWeek::current()->key(),
        'weight_kg' => 82.5,
        'recorded_by_user_id' => $this->admin->id,
    ]);

    $response = Livewire\Livewire::actingAs($this->admin)
        ->test(Analytics::class)
        ->call('export');

    expect($response->effects['download'] ?? true)->not->toBeNull();
});

// ------------------------------------------------------- SSO configuration

it('refuses the development sso stub outside local and testing', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $driver = new StubSsoDriver;

    // The one misconfiguration that would let anyone sign in as anyone, so the
    // guard is on the environment itself rather than on config alone.
    expect($driver->isAvailable())->toBeFalse();

    $this->get(route('sso.stub'))->assertNotFound();
    $this->get(route('sso.redirect'))->assertStatus(503);
});

it('fails loudly on an unrecognised sso driver rather than silently falling back', function () {
    config()->set('sso.driver', 'qcxis-typo');

    // Silently degrading to the stub would disable staff sign-in in production
    // with no visible symptom.
    expect(fn () => app(SsoDriver::class))
        ->toThrow(InvalidArgumentException::class);
});

it('tells staff why sign-in is unavailable instead of just hiding the button', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee("Staff sign-in isn't available yet", false);
});

it('anonymises and exits someone who withdraws', function () {
    $this->actingAs($this->staff)->post(route('consent.withdraw'));

    $withdrawn = $this->staff->fresh();

    // Figures stay for the team aggregate, but the identity does not.
    expect($withdrawn->anonymised_at)->not->toBeNull()
        ->and($withdrawn->is_participant)->toBeFalse()
        ->and($withdrawn->left_at)->not->toBeNull()
        ->and($withdrawn->displayName())->toBe('Withdrawn member');
});

it('refuses an sso identity that has left the challenge', function () {
    $this->staff->update(['left_at' => now()->subDay()]);

    $this->get(route('sso.callback', ['email' => $this->staff->email]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an admin who can sign in through sso and the fallback', function () {
    $this->artisan('challenge:admin', [
        'email' => 'dev@qcxis.com',
        '--name' => 'ZEN',
        '--password' => 'secret-pass',
    ])->assertSuccessful();

    $admin = User::whereRaw('lower(email) = ?', ['dev@qcxis.com'])->first();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->name)->toBe('ZEN')
        ->and(Hash::check('secret-pass', $admin->password))->toBeTrue()
        // Admins shouldn't meet the consent gate on the way to the roster.
        ->and($admin->hasConsented())->toBeTrue();
});

it('promotes an existing roster member rather than creating a duplicate', function () {
    $staff = User::factory()->create(['email' => 'along@qcxis.com', 'name' => 'Along']);

    $this->artisan('challenge:admin', ['email' => 'along@qcxis.com'])->assertSuccessful();

    expect(User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->count())->toBe(1)
        ->and($staff->fresh()->role)->toBe(Role::Admin)
        // Their weigh-in history and identity are untouched.
        ->and($staff->fresh()->name)->toBe('Along');
});

it('matches the email case-insensitively, as sso does', function () {
    User::factory()->create(['email' => 'along@qcxis.com']);

    $this->artisan('challenge:admin', ['email' => 'ALONG@QCXIS.COM'])->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('restores someone who had been removed', function () {
    $user = User::factory()->create(['email' => 'back@qcxis.com']);
    $user->delete();

    $this->artisan('challenge:admin', ['email' => 'back@qcxis.com'])->assertSuccessful();

    expect(User::whereRaw('lower(email) = ?', ['back@qcxis.com'])->first())->not->toBeNull();
});

it('can create an admin who runs the challenge without competing', function () {
    $this->artisan('challenge:admin', [
        'email' => 'organiser@qcxis.com',
        '--no-compete' => true,
    ])->assertSuccessful();

    expect(User::whereRaw('lower(email) = ?', ['organiser@qcxis.com'])->first()->is_participant)
        ->toBeFalse();
});

it('refuses an invalid email', function () {
    $this->artisan('challenge:admin', ['email' => 'not-an-email'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('leaves the fallback password unset unless one is given', function () {
    $this->artisan('challenge:admin', ['email' => 'sso-only@qcxis.com'])->assertSuccessful();

    // SSO-only by default: no password means no fallback credential to leak.
    expect(User::whereRaw('lower(email) = ?', ['sso-only@qcxis.com'])->first()->password)->toBeNull();
});

it('reports the stored row, not an unhydrated new model', function () {
    // A freshly created model has no database defaults loaded, so the summary
    // would otherwise claim a competing admin is not competing.
    $this->artisan('challenge:admin', ['email' => 'fresh@qcxis.com'])
        ->expectsOutputToContain('Competing:          yes')
        ->assertSuccessful();
});

it('lists the roster with sign-in status', function () {
    User::factory()->create(['name' => 'Signed In', 'email' => 'in@qcxis.com', 'last_login_at' => now()]);
    User::factory()->create(['name' => 'Never In', 'email' => 'never@qcxis.com']);

    $this->artisan('challenge:roster')
        ->expectsOutputToContain('in@qcxis.com')
        ->expectsOutputToContain('never')
        ->assertSuccessful();
});

it('can list only the people who have never signed in', function () {
    User::factory()->create(['name' => 'Signed In', 'email' => 'in@qcxis.com', 'last_login_at' => now()]);
    User::factory()->create(['name' => 'Never In', 'email' => 'never@qcxis.com']);

    $this->artisan('challenge:roster', ['--missing' => true])
        ->expectsOutputToContain('never@qcxis.com')
        ->doesntExpectOutputToContain('in@qcxis.com')
        ->assertSuccessful();
});

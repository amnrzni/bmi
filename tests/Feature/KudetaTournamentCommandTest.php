<?php

use App\Enums\Division;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;

it('creates the tournament with its teams, matches and programme, once', function () {
    $this->artisan('tournaments:kudeta')->assertSuccessful();
    $this->artisan('tournaments:kudeta')->assertSuccessful();

    $tournament = Tournament::sole();

    expect($tournament->slug)->toBe('kudeta-bola-baling')
        ->and($tournament->teams()->pluck('name')->all())->toBe(['TEAM A&B', 'TEAM C&D', 'TEAM E&F', 'TEAM G&H'])
        ->and($tournament->fixtures()->count())->toBe(6)
        ->and($tournament->programmeRows())->toHaveCount(11)
        ->and($tournament->programmeRows()[9]['kind'])->toBe('final');
});

it('places only names that match exactly one roster member', function () {
    $haikal = User::factory()->create(['name' => 'Muhammad Haikal bin Ahmad']);
    $baizura = User::factory()->create(['name' => 'Baizura Hamid']);
    $hamirul = User::factory()->create(['name' => 'Hamirul Nizam']);
    // "SYED" matches both of these, so neither is placed.
    User::factory()->create(['name' => 'Syed Ali']);
    User::factory()->create(['name' => 'Syed Omar']);
    // The only match for both "Farah" and "Hanim" — can't be both people.
    $farahHanim = User::factory()->create(['name' => 'Farah Hanim']);
    // Someone who has left is never matched.
    User::factory()->leftOn('2026-09-01')->create(['name' => 'Raziq Leaver']);

    $this->artisan('tournaments:kudeta')->assertSuccessful();

    $players = TournamentPlayer::with('team')->get()->keyBy('user_id');

    expect($players)->toHaveCount(3)
        ->and($players[$haikal->id]->team->name)->toBe('TEAM A&B')
        ->and($players[$haikal->id]->division)->toBe(Division::Men)
        ->and($players[$haikal->id]->is_captain)->toBeTrue()
        ->and($players[$baizura->id]->division)->toBe(Division::Women)
        ->and($players[$hamirul->id]->is_out)->toBeTrue()
        ->and($players[$hamirul->id]->is_captain)->toBeFalse()
        ->and($players->has($farahHanim->id))->toBeFalse();
});

it('picks up new matches on a re-run without touching people already placed', function () {
    $haikal = User::factory()->create(['name' => 'Haikal']);
    $this->artisan('tournaments:kudeta')->assertSuccessful();

    TournamentPlayer::where('user_id', $haikal->id)->update(['is_captain' => false]);
    User::factory()->create(['name' => 'Raziq']);

    $this->artisan('tournaments:kudeta')->assertSuccessful();

    expect(TournamentPlayer::count())->toBe(2)
        ->and(TournamentPlayer::firstWhere('user_id', $haikal->id)->is_captain)->toBeFalse();
});

it('writes nothing on a dry run', function () {
    User::factory()->create(['name' => 'Haikal']);

    $this->artisan('tournaments:kudeta', ['--dry-run' => true])->assertSuccessful();

    expect(Tournament::count())->toBe(0)
        ->and(TournamentPlayer::count())->toBe(0);
});

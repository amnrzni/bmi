<?php

use App\Enums\BmiCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\ProgressService;
use App\Support\ChallengeWeek;

/**
 * The awkward cases are the point of this file. Personal baselines, gaps,
 * latecomers and leavers are exactly where the handoff says this goes wrong,
 * so each one gets an explicit test.
 */
beforeEach(function () {
    $this->service = app(ProgressService::class);
});

/**
 * The admin who enters the numbers. Non-participant here so this fixture never
 * silently adds a competitor to roster counts — real admins do compete.
 */
function recorder(): User
{
    return User::withoutGlobalScopes()->firstWhere('email', 'pic@test.local')
        ?? User::factory()->admin()->nonParticipant()->create(['email' => 'pic@test.local']);
}

/** @param  array<int,float>  $weekToWeight  keyed by 1-based challenge week */
function staffWithSeries(array $weekToWeight, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    foreach ($weekToWeight as $weekNumber => $weight) {
        WeighIn::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => ChallengeWeek::fromNumber($weekNumber)->key(),
            'weight_kg' => $weight,
            'bmi' => $user->bmiFor($weight),
            'recorded_by_user_id' => recorder()->id,
        ]);
    }

    return $user->load('weighIns');
}

// ------------------------------------------------------------------- baseline

it('uses the person own first record as baseline, not challenge week 1', function () {
    // Joined late: first weigh-in is week 3, so week 3 is their zero.
    $user = staffWithSeries([3 => 90.0, 4 => 88.0, 5 => 87.0]);

    $baseline = $this->service->baseline($user);

    expect($baseline->week_start_date->toDateString())
        ->toBe(ChallengeWeek::fromNumber(3)->key())
        ->and((float) $baseline->weight_kg)->toBe(90.0);
});

it('measures delta from the first record to the latest', function () {
    $user = staffWithSeries([1 => 100.0, 2 => 98.5, 3 => 96.0]);

    $summary = $this->service->summary($user);

    expect($summary->baselineWeight)->toBe(100.0)
        ->and($summary->currentWeight)->toBe(96.0)
        ->and($summary->deltaWeight)->toBe(-4.0)
        ->and($summary->weeksRecorded)->toBe(3);
});

it('reports percent change as the ranking metric', function () {
    $user = staffWithSeries([1 => 100.0, 2 => 95.0]);

    expect($this->service->percentChange($user))->toBe(-5.0);
});

it('reports a gain as a positive percent change', function () {
    $user = staffWithSeries([1 => 80.0, 2 => 82.0]);

    expect($this->service->percentChange($user))->toBe(2.5);
});

it('returns no progress figures for someone who never weighed in', function () {
    $user = User::factory()->create()->load('weighIns');

    $summary = $this->service->summary($user);

    expect($summary->weeksRecorded)->toBe(0)
        ->and($summary->baselineWeight)->toBeNull()
        ->and($summary->deltaWeight)->toBeNull()
        ->and($summary->percentChange)->toBeNull()
        ->and($summary->hasData())->toBeFalse();
});

// ------------------------------------------------------------ week-over-week

it('compares week-over-week against the previous actual record, skipping gaps', function () {
    // Missed weeks 2 and 4 entirely.
    $user = staffWithSeries([1 => 100.0, 3 => 97.0, 5 => 95.5]);

    $deltas = $this->service->weekOverWeek($user);

    // Week 3 compares to week 1 (its previous ACTUAL record), not to a missing week 2.
    expect($deltas->get(ChallengeWeek::fromNumber(3)->key()))->toBe(-3.0)
        ->and($deltas->get(ChallengeWeek::fromNumber(5)->key()))->toBe(-1.5);
});

it('gives the first record no week-over-week value', function () {
    $user = staffWithSeries([2 => 90.0, 3 => 89.0]);

    $deltas = $this->service->weekOverWeek($user);

    // Rendered as "·" in the grid — there is nothing to compare against.
    expect($deltas->get(ChallengeWeek::fromNumber(2)->key()))->toBeNull()
        ->and($deltas->get(ChallengeWeek::fromNumber(3)->key()))->toBe(-1.0);
});

// -------------------------------------------------------------- single record

it('treats a single record as zero change and does not rank it', function () {
    $user = staffWithSeries([1 => 100.0]);

    $summary = $this->service->summary($user);

    expect($summary->deltaWeight)->toBe(0.0)
        ->and($summary->percentChange)->toBe(0.0)
        ->and($summary->isRanked)->toBeFalse()
        ->and($this->service->countsTowardTeam($user))->toBeFalse();
});

it('ranks a person only once they hit the minimum record count', function () {
    config()->set('challenge.min_records_rank', 4);

    expect($this->service->isRanked(staffWithSeries([1 => 90.0, 2 => 89.0, 3 => 88.0])))->toBeFalse()
        ->and($this->service->isRanked(staffWithSeries([1 => 90.0, 2 => 89.0, 3 => 88.0, 4 => 87.0])))->toBeTrue();
});

// ---------------------------------------------------------------------- BMI

it('tracks weight but not BMI when height was never captured', function () {
    $user = staffWithSeries([1 => 100.0, 2 => 97.0], ['height_cm' => null]);

    $summary = $this->service->summary($user);

    expect($summary->deltaWeight)->toBe(-3.0)
        ->and($summary->baselineBmi)->toBeNull()
        ->and($summary->currentBmi)->toBeNull()
        ->and($summary->deltaBmi)->toBeNull();
});

it('recomputes historical BMI when a height is corrected', function () {
    $user = staffWithSeries([1 => 100.0, 2 => 96.0], ['height_cm' => 170]);

    expect($this->service->summary($user)->baselineBmi)->toBe(34.6);

    // Admin fixes a typo in the height. History follows — accepted trade-off.
    $user->update(['height_cm' => 180]);

    expect($this->service->summary($user->fresh()->load('weighIns'))->baselineBmi)->toBe(30.86);
});

it('computes BMI on the Malaysian cutoffs', function () {
    // 70kg at 170cm = 24.22 — "normal" under WHO, "overweight" here.
    expect(BmiCategory::forBmi(24.22))->toBe(BmiCategory::Overweight)
        ->and(BmiCategory::forBmi(22.9))->toBe(BmiCategory::Normal)
        ->and(BmiCategory::forBmi(27.5))->toBe(BmiCategory::Obese)
        ->and(BmiCategory::forBmi(18.4))->toBe(BmiCategory::Underweight);
});

// ------------------------------------------------------------- team standings

it('averages percent change rather than raw kilograms', function () {
    $team = Team::factory()->create(['code' => 'TAH']);

    // A loses 5kg from 100 (-5%); B loses 2kg from 80 (-2.5%).
    // Raw kg would say A is twice as good; percent says -3.75% average.
    staffWithSeries([1 => 100.0, 2 => 95.0], ['team_id' => $team->id]);
    staffWithSeries([1 => 80.0, 2 => 78.0], ['team_id' => $team->id]);

    $standing = $this->service->teamStanding($team);

    expect($standing->averagePercentChange)->toBe(-3.75)
        ->and($standing->countedMembers)->toBe(2);
});

it('excludes members without enough records from the team average', function () {
    config()->set('challenge.min_records_team', 2);
    $team = Team::factory()->create(['code' => 'BKN']);

    staffWithSeries([1 => 100.0, 2 => 95.0], ['team_id' => $team->id]); // -5%
    staffWithSeries([1 => 90.0], ['team_id' => $team->id]);             // single record, 0%

    $standing = $this->service->teamStanding($team);

    // Excluding the single-record member keeps the average at -5% rather than
    // diluting it to -2.5% for reasons unrelated to effort.
    expect($standing->averagePercentChange)->toBe(-5.0)
        ->and($standing->memberCount)->toBe(2)
        ->and($standing->countedMembers)->toBe(1);
});

it('reports no team average when nobody has enough data yet', function () {
    $team = Team::factory()->create(['code' => 'TAH']);
    staffWithSeries([1 => 100.0], ['team_id' => $team->id]);

    $standing = $this->service->teamStanding($team);

    expect($standing->averagePercentChange)->toBeNull()
        ->and($standing->hasData())->toBeFalse()
        ->and($standing->memberCount)->toBe(1);
});

// ----------------------------------------------------------------- compliance

it('lists who is missing for a week as roster minus recorded', function () {
    $week = ChallengeWeek::fromNumber(1);

    staffWithSeries([1 => 80.0], ['name' => 'Recorded']);
    User::factory()->create(['name' => 'Missing']);

    $report = $this->service->compliance($week);

    expect($report->expectedCount())->toBe(2)
        ->and($report->recordedCount())->toBe(1)
        ->and($report->missing->pluck('name')->all())->toBe(['Missing'])
        ->and($report->isComplete())->toBeFalse();
});

it('stops flagging someone as missing after they leave', function () {
    $week = ChallengeWeek::fromNumber(3);

    // Left during week 1 — keeps their logged weeks, generates no missing flags after.
    User::factory()->leftOn(ChallengeWeek::fromNumber(1)->key())->create(['name' => 'Leaver']);
    User::factory()->create(['name' => 'Still here']);

    $report = $this->service->compliance($week);

    expect($report->expectedCount())->toBe(1)
        ->and($report->missing->pluck('name')->all())->toBe(['Still here']);
});

it('does not expect a weigh-in before someone joined', function () {
    $week = ChallengeWeek::fromNumber(1);

    User::factory()->joinedOn(ChallengeWeek::fromNumber(6)->key())->create(['name' => 'Latecomer']);
    User::factory()->create(['name' => 'Founder']);

    $report = $this->service->compliance($week);

    expect($report->missing->pluck('name')->all())->toBe(['Founder']);
});

it('leaves non-participants and removed staff out of the roster', function () {
    $week = ChallengeWeek::fromNumber(1);

    User::factory()->nonParticipant()->create(['name' => 'Organiser']);
    User::factory()->create(['name' => 'Deleted'])->delete();
    User::factory()->create(['name' => 'Competitor']);

    $report = $this->service->compliance($week);

    expect($report->expectedCount())->toBe(1)
        ->and($report->missing->pluck('name')->all())->toBe(['Competitor']);
});

it('keeps a leaver historical records intact', function () {
    // Roster edits must never rewrite history (HANDOFF.md §5.3).
    $user = staffWithSeries([1 => 100.0, 2 => 98.0], ['name' => 'Leaver']);
    $user->update(['left_at' => ChallengeWeek::fromNumber(3)->key()]);

    expect($this->service->recordCount($user->fresh()->load('weighIns')))->toBe(2);
});

<?php

use App\Livewire\WeighInSession;
use App\Models\Department;
use App\Models\User;
use App\Models\WeighIn;
use App\Support\ChallengeWeek;
use Livewire\Livewire;

/**
 * The operation this app exists to perform first.
 *
 * Week 1 (10 Aug 2026) ran before the app existed, so its normal Monday–Wednesday
 * window closes with no data in it. An admin must still be able to enter it.
 */
beforeEach(function () {
    $this->department = Department::factory()->create(['name' => 'Operations']);

    $this->admin = User::factory()->admin()->nonParticipant()
        ->create(['department_id' => $this->department->id]);

    $this->staff = User::factory()->create([
        'name' => 'Along',
        'department_id' => $this->department->id,
        'height_cm' => 172,
    ]);
});

it('lets an admin backfill a week whose window has already closed', function () {
    $weekOne = ChallengeWeek::fromNumber(1);

    // Jump to the middle of week 3 — week 1 closed a fortnight ago.
    $this->travelTo($weekOne->startDate->addWeeks(2)->addDays(3));

    expect($weekOne->isOpen())->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('selectWeek', $weekOne->key())
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '84.5')
        ->call('save')
        ->assertHasNoErrors();

    $record = WeighIn::where('user_id', $this->staff->id)->first();

    expect($record)->not->toBeNull()
        ->and($record->week_start_date->toDateString())->toBe($weekOne->key())
        ->and((float) $record->weight_kg)->toBe(84.5)
        // Derived server-side from the height on file: 84.5 / 1.72² = 28.56
        ->and((float) $record->bmi)->toBe(28.56)
        // Attributable, so data entry is never anonymous.
        ->and($record->recorded_by_user_id)->toBe($this->admin->id);
});

it('refuses to record a week that has not started yet', function () {
    $future = ChallengeWeek::current()->next();

    $component = Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('selectWeek', $future->key())
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '84.5')
        ->call('save');

    expect(WeighIn::count())->toBe(0);
    $component->assertSet('flash', 'This week is locked.');
});

it('corrects rather than duplicates when a week is saved twice', function () {
    $week = ChallengeWeek::current();

    $session = Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '84.5')
        ->call('save');

    $session->set("weights.{$this->staff->id}", '83.0')->call('save');

    expect(WeighIn::count())->toBe(1)
        ->and((float) WeighIn::first()->weight_kg)->toBe(83.0);
});

it('removes the record when a weight is cleared', function () {
    Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '84.5')
        ->call('save')
        ->set("weights.{$this->staff->id}", '')
        ->call('save');

    expect(WeighIn::count())->toBe(0);
});

it('rejects an implausible weight without discarding the rest of the batch', function () {
    $colleague = User::factory()->create([
        'name' => 'Dragon',
        'department_id' => $this->department->id,
        'height_cm' => 180,
    ]);

    Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '845')   // a slipped decimal point
        ->set("weights.{$colleague->id}", '95.0')
        ->call('save')
        ->assertHasErrors('weights');

    // The good row still saved — one typo doesn't cost the whole department.
    expect(WeighIn::count())->toBe(1)
        ->and(WeighIn::first()->user_id)->toBe($colleague->id);
});

it('warns on a big jump but still records it', function () {
    $week = ChallengeWeek::current();

    WeighIn::factory()->create([
        'user_id' => $this->staff->id,
        'week_start_date' => $week->previous()->key(),
        'weight_kg' => 84.0,
        'recorded_by_user_id' => $this->admin->id,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('openDepartment', $this->department->id)
        ->set("weights.{$this->staff->id}", '76.0')
        ->call('save')
        ->assertHasNoErrors();

    // Real people do occasionally drop 8kg; the warning informs, it doesn't block.
    expect($component->get('warnings'))->toHaveKey($this->staff->id)
        ->and((float) WeighIn::whereDate('week_start_date', $week->key())->first()->weight_kg)->toBe(76.0);
});

it('keeps a non-admin out of a closed week', function () {
    $weekOne = ChallengeWeek::fromNumber(1);
    $this->travelTo($weekOne->startDate->addWeeks(2));

    // Staff can't reach this screen at all — the route is admin-only.
    $this->actingAs($this->staff)->get(route('weigh-in'))->assertForbidden();
});

it('counts progress against the open department only, not the whole week', function () {
    $otherDepartment = Department::factory()->create(['name' => 'Finance']);
    $elsewhere = User::factory()->create(['department_id' => $otherDepartment->id]);

    $week = ChallengeWeek::current();

    // Someone in another department is already recorded for this week.
    WeighIn::factory()->create([
        'user_id' => $elsewhere->id,
        'week_start_date' => $week->key(),
        'recorded_by_user_id' => $this->admin->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test(WeighInSession::class)
        ->call('openDepartment', $this->department->id)
        ->assertSee('0 / 1 recorded')
        ->assertDontSee('1 / 1 recorded');
});

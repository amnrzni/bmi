<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\WeighInRecorder;
use App\Support\ChallengeWeek;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * PIC batch entry, two steps: pick a department, then run down its roster.
 *
 * Phone-first — this is used standing next to a scale, not at a desk.
 *
 * The week selector is what makes week 1 recoverable at all: the app didn't
 * exist during its Mon–Wed window, so an admin has to be able to enter a week
 * that is already closed.
 */
#[Layout('components.layouts.app')]
#[Title('Weigh-In — BMI Challenge')]
class WeighInSession extends Component
{
    #[Url(as: 'week')]
    public ?string $weekKey = null;

    #[Url(as: 'dept')]
    public ?int $departmentId = null;

    /** userId => weight as typed. */
    public array $weights = [];

    public ?string $flash = null;

    /** userId => "that's a big jump" notice; warnings never block a save. */
    public array $warnings = [];

    public function mount(): void
    {
        $this->weekKey ??= ChallengeWeek::current()->key();
        $this->loadWeights();
    }

    public function week(): ChallengeWeek
    {
        return ChallengeWeek::fromDate($this->weekKey ?? ChallengeWeek::current()->key());
    }

    /**
     * Admins may edit any elapsed week. Everyone else is limited to the normal
     * Monday–Wednesday window.
     */
    public function canEdit(): bool
    {
        $week = $this->week();

        if (! $week->hasElapsed()) {
            return false;
        }

        return auth()->user()->isAdmin() || $week->isOpen();
    }

    public function selectWeek(string $weekKey): void
    {
        $this->weekKey = ChallengeWeek::fromDate($weekKey)->key();
        $this->warnings = [];
        $this->flash = null;
        $this->loadWeights();
    }

    public function openDepartment(int $departmentId): void
    {
        $this->departmentId = $departmentId;
        $this->warnings = [];
        $this->flash = null;
        $this->loadWeights();
    }

    public function closeDepartment(): void
    {
        $this->departmentId = null;
        $this->warnings = [];
    }

    /** Pre-fill with whatever is already recorded, so a re-open is a correction. */
    private function loadWeights(): void
    {
        $this->weights = WeighIn::query()
            ->forWeek($this->week())
            ->pluck('weight_kg', 'user_id')
            ->map(fn ($kg) => rtrim(rtrim((string) $kg, '0'), '.'))
            ->all();
    }

    public function save(WeighInRecorder $recorder): void
    {
        if (! $this->canEdit()) {
            $this->flash = 'This week is locked.';

            return;
        }

        $week = $this->week();
        $roster = $this->roster();
        $saved = 0;
        $cleared = 0;
        $errors = [];
        $this->warnings = [];

        foreach ($roster as $person) {
            $raw = trim((string) ($this->weights[$person->id] ?? ''));

            if ($raw === '') {
                // Clearing a field removes the record rather than storing a zero.
                $recorder->remove($person, $week);
                $cleared++;

                continue;
            }

            if (! is_numeric($raw)) {
                $errors[] = "{$person->name}: \"{$raw}\" isn't a number.";

                continue;
            }

            $weight = (float) $raw;

            try {
                if ($warning = $recorder->jumpWarning($person, $week, $weight)) {
                    $this->warnings[$person->id] = $warning;
                }

                $recorder->record($person, $week, $weight, auth()->user());
                $saved++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = "{$person->name}: {$exception->getMessage()}";
            }
        }

        if ($errors !== []) {
            $this->addError('weights', implode(' ', $errors));
        }

        $this->flash = trim(sprintf(
            '%d recorded for %s.%s',
            $saved,
            $week->label(),
            $cleared > 0 ? " {$cleared} left blank." : '',
        ));
    }

    /** @return Collection<int,User> */
    public function roster(): Collection
    {
        if (! $this->departmentId) {
            return collect();
        }

        return User::query()
            ->onRosterFor($this->week())
            ->where('department_id', $this->departmentId)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $week = $this->week();

        $recordedByDepartment = User::query()
            ->onRosterFor($week)
            ->whereNotNull('department_id')
            ->whereIn('id', WeighIn::forWeek($week)->pluck('user_id'))
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $expectedByDepartment = User::query()
            ->onRosterFor($week)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $departments = Department::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Department $department) => [
                'model' => $department,
                'expected' => (int) ($expectedByDepartment[$department->id] ?? 0),
                'recorded' => (int) ($recordedByDepartment[$department->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['expected'] > 0)
            ->values();

        return view('livewire.weigh-in-session', [
            'week' => $week,
            'weeks' => ChallengeWeek::elapsed()->reverse()->values(),
            'departments' => $departments,
            'roster' => $this->roster(),
            'editable' => $this->canEdit(),
        ]);
    }
}

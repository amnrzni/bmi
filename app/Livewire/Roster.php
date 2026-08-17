<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\WeighInRecorder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Master roster: who is in the challenge, their email, height and team.
 *
 * Email carries the most weight of any field here — it is the sole key matching
 * a QCXIS identity to a roster row, so a typo silently locks that person out
 * with an error that blames the roster rather than the typo. Hence the
 * lowercasing on write, the case-insensitive uniqueness check, and the
 * "never signed in" flag.
 */
#[Layout('components.layouts.app')]
#[Title('Roster — BMI Challenge')]
class Roster extends Component
{
    /** userId => height in cm, bound to the inline inputs. */
    public array $heights = [];

    public ?string $flash = null;

    // ------------------------------------------------------------ staff form

    public bool $showStaffForm = false;

    public ?int $editingUserId = null;

    #[Validate('required|string|max:255')]
    public string $staffName = '';

    #[Validate('required|email|max:255')]
    public string $staffEmail = '';

    public ?int $staffDepartmentId = null;

    public ?int $staffTeamId = null;

    #[Validate('nullable|integer|between:100,250')]
    public ?string $staffHeight = null;

    // ------------------------------------------------------- department form

    public bool $showDeptForm = false;

    public ?int $editingDeptId = null;

    #[Validate('required|string|max:255')]
    public string $deptName = '';

    // ------------------------------------------------------------- team form

    public bool $showTeamForm = false;

    public ?int $editingTeamId = null;

    #[Validate('required|string|max:255')]
    public string $teamName = '';

    public function mount(): void
    {
        // Defence in depth: the route is admin-only, but a Livewire component
        // that mutates the roster shouldn't rely on that alone. No method is
        // reachable without a successful mount.
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->heights = User::participants()
            ->pluck('height_cm', 'id')
            ->map(fn (?int $cm) => $cm ? (string) $cm : '')
            ->all();
    }

    // ------------------------------------------------------------------ staff

    public function newStaff(): void
    {
        $this->resetStaffForm();
        $this->showStaffForm = true;
    }

    public function editStaff(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->staffName = $user->name;
        $this->staffEmail = $user->email;
        $this->staffDepartmentId = $user->department_id;
        $this->staffTeamId = $user->team_id;
        $this->staffHeight = $user->height_cm ? (string) $user->height_cm : null;
        $this->showStaffForm = true;
    }

    public function saveStaff(WeighInRecorder $recorder): void
    {
        $this->validateOnly('staffName');
        $this->validateOnly('staffEmail');
        $this->validateOnly('staffHeight');

        $email = mb_strtolower(trim($this->staffEmail));

        // Case-insensitive, and against trashed rows too: the unique index
        // covers soft-deleted users, so a plain check would pass and then the
        // insert would fail with a database error.
        $clash = User::withTrashed()
            ->whereRaw('lower(email) = ?', [$email])
            ->when($this->editingUserId, fn ($query) => $query->where('id', '!=', $this->editingUserId))
            ->exists();

        if ($clash) {
            $this->addError('staffEmail', 'Someone on the roster already uses that email.');

            return;
        }

        $attributes = [
            'name' => trim($this->staffName),
            'email' => $email,
            'department_id' => $this->staffDepartmentId ?: null,
            'team_id' => $this->staffTeamId ?: null,
            'height_cm' => $this->staffHeight !== null && $this->staffHeight !== ''
                ? (int) $this->staffHeight
                : null,
        ];

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $emailChanged = $user->email !== $email;
            $user->update($attributes);

            // A height correction re-derives their stored BMI history.
            $recorder->recalculateBmisFor($user);

            $this->flash = "Saved {$user->name}.";

            if ($emailChanged && ! $user->hasNeverSignedIn()) {
                $this->flash .= ' They have signed in before, so they must now use the new address.';
            }
        } else {
            $user = User::create([...$attributes, 'is_participant' => true]);
            $this->flash = "Added {$user->name}. They can sign in once QCXIS returns this exact email.";
        }

        $this->heights[$user->id] = $user->height_cm ? (string) $user->height_cm : '';
        $this->resetStaffForm();
        $this->showStaffForm = false;
    }

    public function cancelStaff(): void
    {
        $this->resetStaffForm();
        $this->showStaffForm = false;
    }

    /**
     * The normal way someone leaves. Keeps every recorded week, stops them
     * generating "missing" flags, and blocks further sign-in — a roster edit
     * must never rewrite history.
     */
    public function markAsLeft(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['left_at' => now()->toDateString()]);

        $this->flash = "{$user->name} is marked as having left. Their recorded weeks are kept.";
    }

    public function restoreStaff(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['left_at' => null]);

        $this->flash = "{$user->name} is back on the roster.";
    }

    /**
     * Only for rows added by mistake. Refused once someone has weigh-ins,
     * because deleting them would retroactively change team averages.
     */
    public function deleteStaff(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->weighIns()->exists()) {
            $this->flash = "{$user->name} has recorded weigh-ins, so they can't be deleted. Mark them as left instead.";

            return;
        }

        $name = $user->name;
        $user->delete();

        $this->flash = "Deleted {$name}.";
    }

    /**
     * Grants or removes admin. Every admin already has full power over the
     * roster (edit weights, manage teams, see everyone's data — HANDOFF §4's
     * accepted PIC-into-admin trade-off), so letting any admin promote another
     * isn't a new tier of trust. Two rails stop the obvious footguns:
     * demoting yourself, and demoting the last admin standing.
     */
    public function toggleAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);
        $promoting = ! $user->isAdmin();

        if (! $promoting) {
            if ($user->id === auth()->id()) {
                $this->flash = "You can't remove your own admin access.";

                return;
            }

            if (User::admins()->count() <= 1) {
                $this->flash = 'At least one admin has to remain.';

                return;
            }
        }

        $user->update(['role' => $promoting ? Role::Admin : Role::Staff]);

        $this->flash = $promoting ? "{$user->name} is now an admin." : "{$user->name} is no longer an admin.";
    }

    private function resetStaffForm(): void
    {
        $this->reset([
            'editingUserId', 'staffName', 'staffEmail',
            'staffDepartmentId', 'staffTeamId', 'staffHeight',
        ]);
        $this->resetValidation();
    }

    // ------------------------------------------------------------ departments

    public function newDepartment(): void
    {
        $this->reset(['editingDeptId', 'deptName']);
        $this->resetValidation();
        $this->showDeptForm = true;
    }

    public function editDepartment(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);

        $this->editingDeptId = $department->id;
        $this->deptName = $department->name;
        $this->showDeptForm = true;
    }

    public function saveDepartment(): void
    {
        $this->validateOnly('deptName');

        if ($this->editingDeptId) {
            Department::findOrFail($this->editingDeptId)->update(['name' => trim($this->deptName)]);
            $this->flash = 'Department renamed.';
        } else {
            Department::create([
                'name' => trim($this->deptName),
                'sort_order' => (int) Department::max('sort_order') + 1,
            ]);
            $this->flash = 'Department added.';
        }

        $this->reset(['editingDeptId', 'deptName']);
        $this->showDeptForm = false;
    }

    /** Refused while anyone is still in it — a delete shouldn't silently orphan staff. */
    public function deleteDepartment(int $departmentId): void
    {
        $department = Department::withCount('staff')->findOrFail($departmentId);

        if ($department->staff_count > 0) {
            $this->flash = "{$department->name} still has {$department->staff_count} staff. Move them first.";

            return;
        }

        $name = $department->name;
        $department->delete();

        $this->flash = "Deleted {$name}.";
    }

    public function moveDepartment(int $departmentId, int $direction): void
    {
        $ordered = Department::orderBy('sort_order')->orderBy('name')->get()->values();
        $index = $ordered->search(fn (Department $d) => $d->id === $departmentId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        $list = $ordered->all();
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];

        // Renumber the whole sequence rather than swapping two values: seeded
        // rows share a sort_order, so a swap alone wouldn't change the order.
        foreach ($list as $position => $department) {
            $department->update(['sort_order' => $position]);
        }
    }

    // ------------------------------------------------------------------ teams

    public function newTeam(): void
    {
        $this->reset(['editingTeamId', 'teamName']);
        $this->resetValidation();
        $this->showTeamForm = true;
    }

    public function editTeam(int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        $this->editingTeamId = $team->id;
        $this->teamName = $team->name;
        $this->showTeamForm = true;
    }

    public function saveTeam(): void
    {
        $this->validateOnly('teamName');

        $name = trim($this->teamName);

        if ($this->editingTeamId) {
            $team = Team::findOrFail($this->editingTeamId);
            // Re-derive the code so a renamed team's tag stays sensible, keeping
            // its own current code out of the collision check.
            $team->update(['name' => $name, 'code' => Team::codeFor($name, $team->id)]);
            $this->flash = 'Team renamed.';
        } else {
            Team::create([
                'name' => $name,
                'code' => Team::codeFor($name),
                'sort_order' => (int) Team::max('sort_order') + 1,
            ]);
            $this->flash = 'Team added.';
        }

        $this->reset(['editingTeamId', 'teamName']);
        $this->showTeamForm = false;
    }

    /** Refused while anyone is still on it — deleting would silently unassign them. */
    public function deleteTeam(int $teamId): void
    {
        $team = Team::withCount('members')->findOrFail($teamId);

        if ($team->members_count > 0) {
            $this->flash = "{$team->name} still has {$team->members_count} members. Move them first.";

            return;
        }

        $name = $team->name;
        $team->delete();

        $this->flash = "Deleted {$name}.";
    }

    public function moveTeam(int $teamId, int $direction): void
    {
        $ordered = Team::orderBy('sort_order')->orderBy('name')->get()->values();
        $index = $ordered->search(fn (Team $t) => $t->id === $teamId);

        if ($index === false) {
            return;
        }

        $target = $index + $direction;

        if ($target < 0 || $target >= $ordered->count()) {
            return;
        }

        $list = $ordered->all();
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];

        foreach ($list as $position => $team) {
            $team->update(['sort_order' => $position]);
        }
    }

    // ------------------------------------------------------- existing actions

    public function setTeam(int $userId, mixed $teamId = null): void
    {
        $user = User::findOrFail($userId);
        // The dropdown sends '' for unassigned; normalise to null.
        $teamId = $teamId !== '' && $teamId !== null ? (int) $teamId : null;
        $user->update(['team_id' => $teamId]);

        $this->flash = "{$user->name} moved to ".($teamId ? $user->fresh()->team->name : 'unassigned').'.';
    }

    public function saveHeight(int $userId, WeighInRecorder $recorder): void
    {
        $user = User::findOrFail($userId);
        $raw = trim((string) ($this->heights[$userId] ?? ''));

        if ($raw === '') {
            $user->update(['height_cm' => null]);
            $recorder->recalculateBmisFor($user);
            $this->flash = "Height cleared for {$user->name}. BMI can't be shown without it.";

            return;
        }

        $validated = $this->validate(
            ['heights.'.$userId => ['required', 'integer', 'between:100,250']],
            ['heights.'.$userId => 'Height must be between 100 and 250 cm.'],
        );

        $user->update(['height_cm' => (int) $validated['heights'][$userId]]);
        $recorder->recalculateBmisFor($user);

        $this->flash = "Height saved for {$user->name}. Their past BMI figures were recalculated.";
    }

    // ----------------------------------------------------------------- render

    public function render(ProgressService $progress)
    {
        $departments = Department::query()
            ->with(['staff' => fn ($query) => $query->participants()->orderBy('name')->with(['weighIns', 'team'])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $unassigned = User::participants()
            ->whereNull('department_id')
            ->with(['weighIns', 'team'])
            ->orderBy('name')
            ->get();

        // members_count for the management list; the whole participant count,
        // not just those loaded above, so the number is honest.
        $teams = Team::withCount(['members' => fn ($q) => $q->where('is_participant', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $everyone = $departments->flatMap->staff->concat($unassigned);

        return view('livewire.roster', [
            'departments' => $departments,
            'unassigned' => $unassigned,
            'teams' => $teams,
            'balance' => $this->balance($teams, $everyone, $progress),
            'unassignedCount' => $everyone->whereNull('team_id')->count(),
            'neverSignedIn' => $everyone->filter->hasNeverSignedIn()->count(),
            'leftCount' => $everyone->filter->hasLeft()->count(),
            'headcount' => $everyone->count(),
            // The true system-wide count, not just participants shown on this
            // page — a non-participant admin still counts toward "last admin".
            'adminCount' => User::admins()->count(),
        ]);
    }

    /**
     * Headcount and average starting BMI per team.
     *
     * Balance is SCORED on headcount only — weight-loss potential can't be
     * measured at assignment time, so this is defensible, not scientific.
     *
     * @return Collection<int,array{team: Team, count: int, avgBmi: ?float}>
     */
    private function balance(Collection $teams, Collection $everyone, ProgressService $progress): Collection
    {
        return $teams->map(function (Team $team) use ($everyone, $progress) {
            $members = $everyone->where('team_id', $team->id);

            $startingBmis = $members
                ->map(function (User $member) use ($progress) {
                    $baseline = $progress->baseline($member);

                    return $baseline ? $member->bmiFor((float) $baseline->weight_kg) : null;
                })
                ->filter();

            return [
                'team' => $team,
                'count' => $members->count(),
                'avgBmi' => $startingBmis->isEmpty() ? null : round($startingBmis->avg(), 1),
            ];
        });
    }
}

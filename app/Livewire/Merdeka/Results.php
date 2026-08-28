<?php

namespace App\Livewire\Merdeka;

use App\Models\Department;
use App\Models\MerdekaJudge;
use App\Models\MerdekaScore;
use App\Models\User;
use App\Support\MerdekaRubric;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Admin view of the contest: who has filed, every sheet, and the standing.
 *
 * Admins see this at any point, including half-filed — waiting for a full panel
 * would leave nobody able to tell on the day whether a judge is stuck. Judges
 * get no route here at all, so neither can anchor on the other's marks.
 *
 * The panel itself is managed from this screen, since it is the only other
 * Merdeka admin surface and doesn't warrant a third.
 */
#[Layout('components.layouts.merdeka', ['heading' => 'Keputusan'])]
#[Title('Keputusan — Sudut Kemerdekaan 2026')]
class Results extends Component
{
    /** Set while reading one filed scoresheet in full. */
    public ?int $openScoreId = null;

    #[Validate('required|string|max:255')]
    public string $newJudgeName = '';

    /**
     * The whole identity match. SSO compares what QCXIS returns against
     * `users.email` and nothing else, so a typo here reads as "not on the
     * roster" at sign-in, blaming the roster rather than the typo.
     */
    #[Validate('required|email|max:255')]
    public string $newJudgeEmail = '';

    #[Validate('nullable|string|max:255')]
    public string $newJudgeTitle = '';

    public ?string $flash = null;

    public function mount(): void
    {
        // Admin-only, via the Gate::before in AppServiceProvider.
        abort_unless(auth()->user()?->can('view-merdeka-results'), 403);
    }

    // ------------------------------------------------------------ the panel

    public function addJudge(): void
    {
        $this->validate();

        // Lowercased on write so it can never diverge from the comparison the
        // SSO callback makes.
        $email = mb_strtolower(trim($this->newJudgeEmail));

        // withTrashed, because the unique index covers soft-deleted rows: a
        // plain lookup would miss one and the insert would fail as a raw
        // database error instead of a message.
        $user = User::withTrashed()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user) {
            if (MerdekaJudge::where('user_id', $user->id)->exists()) {
                $this->flash = $user->name.' sudah berada dalam panel.';

                return;
            }

            // Deliberately keeps whatever the address already is. If it belongs
            // to a competing staff member, seating them must not quietly strip
            // them out of the challenge by flipping is_participant.
            $user->restore();
        } else {
            // Judges are not roster members. is_participant = false keeps them
            // off the roster screen, the teams, the standings and every
            // weigh-in count — the row exists only because SSO has to match an
            // email to a users row to sign anyone in at all.
            $user = User::create([
                'name' => trim($this->newJudgeName),
                'email' => $email,
                'is_participant' => false,
            ]);
        }

        MerdekaJudge::create([
            'user_id' => $user->id,
            'title' => trim($this->newJudgeTitle) ?: null,
            'sort_order' => (int) MerdekaJudge::max('sort_order') + 1,
        ]);

        $this->reset('newJudgeName', 'newJudgeEmail', 'newJudgeTitle');
        $this->flash = $user->name.' telah ditambah. Mereka boleh log masuk sebaik sahaja QCXIS memulangkan '.$email.' dengan tepat.';
    }

    public function removeJudge(int $judgeId): void
    {
        $judge = MerdekaJudge::with('user')->findOrFail($judgeId);
        $user = $judge->user;
        $name = $user?->name ?? 'Hakim';

        // Only the seat goes. Sheets they already filed stay — they were signed,
        // and the standing was computed with them.
        $judge->delete();

        // An account that existed only to seat a judge goes with the seat.
        // Left behind, a mistyped address is a working SSO login for whoever
        // owns it. Everything else is spared: a roster member, an admin, anyone
        // who has signed in, and anyone who has filed a sheet.
        $wasOnlyEverAJudge = $user
            && ! $user->is_participant
            && ! $user->isAdmin()
            && $user->hasNeverSignedIn()
            && ! MerdekaScore::where('user_id', $user->id)->exists();

        if ($wasOnlyEverAJudge) {
            $user->delete();

            $this->flash = $name.' telah dikeluarkan dari panel dan akaun log masuk mereka dipadam.';

            return;
        }

        $this->flash = $name.' telah dikeluarkan dari panel. Markah yang telah dihantar dikekalkan.';
    }

    // -------------------------------------------------------------- one sheet

    public function openScore(int $scoreId): void
    {
        $this->openScoreId = MerdekaScore::findOrFail($scoreId)->id;
    }

    public function closeScore(): void
    {
        $this->reset('openScoreId');
    }

    // ------------------------------------------------------------------- data

    public function render()
    {
        $judges = MerdekaJudge::with('user')->orderBy('sort_order')->orderBy('id')->get();
        $departments = Department::orderBy('sort_order')->orderBy('name')->get();
        // Department eager-loaded for the detail view; shouldBeStrict is on.
        $scores = MerdekaScore::with(['user', 'department'])->get();

        /** @var Collection<int, Collection<int, MerdekaScore>> $byDepartment */
        $byDepartment = $scores->groupBy('department_id');

        // The standing is the mean of every sheet filed for a corner, not just
        // those from the current panel — a sheet counted the day it was signed
        // shouldn't drop out because someone later left the panel.
        $rows = $departments->map(function (Department $department) use ($byDepartment, $judges) {
            $filed = $byDepartment->get($department->id, collect());
            $panelIds = $judges->pluck('user_id');

            return [
                'department' => $department,
                'filed' => $filed->sortBy(fn (MerdekaScore $s) => $panelIds->search($s->user_id) === false ? PHP_INT_MAX : $panelIds->search($s->user_id))->values(),
                'missing' => $judges->reject(fn (MerdekaJudge $j) => $filed->contains('user_id', $j->user_id))->values(),
                'average' => $filed->isEmpty() ? null : round($filed->sum(fn (MerdekaScore $s) => (float) $s->total) / $filed->count(), 2),
            ];
        });

        $ranked = $rows->filter(fn (array $row) => $row['average'] !== null)
            ->sortByDesc('average')
            ->values();

        return view('livewire.merdeka.results', [
            'judges' => $judges,
            'rows' => $rows,
            'ranked' => $ranked,
            'expected' => $departments->count() * $judges->count(),
            'filedCount' => $scores->count(),
            'departmentsComplete' => $rows->filter(fn (array $row) => $judges->isNotEmpty() && $row['missing']->isEmpty())->count(),
            'departmentCount' => $departments->count(),
            'openScore' => $this->openScoreId
                ? $scores->firstWhere('id', $this->openScoreId)
                : null,
            'criteria' => MerdekaRubric::CRITERIA,
            'scaleLabels' => MerdekaRubric::SCALE_LABELS,
        ]);
    }
}

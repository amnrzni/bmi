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

    public ?int $newJudgeUserId = null;

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
        $this->validate(['newJudgeUserId' => 'required|integer|exists:users,id']);
        $this->validateOnly('newJudgeTitle');

        $user = User::findOrFail($this->newJudgeUserId);

        if (MerdekaJudge::where('user_id', $user->id)->exists()) {
            $this->flash = $user->name.' sudah berada dalam panel.';

            return;
        }

        MerdekaJudge::create([
            'user_id' => $user->id,
            'title' => trim($this->newJudgeTitle) ?: null,
            'sort_order' => (int) MerdekaJudge::max('sort_order') + 1,
        ]);

        $this->reset('newJudgeUserId', 'newJudgeTitle');
        $this->flash = $user->name.' telah ditambah sebagai panel hakim.';
    }

    public function removeJudge(int $judgeId): void
    {
        $judge = MerdekaJudge::with('user')->findOrFail($judgeId);
        $name = $judge->user?->name ?? 'Hakim';

        // Only the seat goes. Sheets they already filed stay — they were signed,
        // and the standing was computed with them.
        $judge->delete();

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
            // Only people not already seated, so the picker can't offer a duplicate.
            'candidates' => User::whereNotIn('id', $judges->pluck('user_id'))->orderBy('name')->get(),
            'openScore' => $this->openScoreId
                ? $scores->firstWhere('id', $this->openScoreId)
                : null,
            'criteria' => MerdekaRubric::CRITERIA,
            'scaleLabels' => MerdekaRubric::SCALE_LABELS,
        ]);
    }
}

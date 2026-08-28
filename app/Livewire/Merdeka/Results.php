<?php

namespace App\Livewire\Merdeka;

use App\Models\Department;
use App\Models\MerdekaJudge;
use App\Models\MerdekaScore;
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
     * The judge's whole credential. They sign in by typing it, so a typo here
     * doesn't lock them out quietly — it hands the seat to nobody at all.
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
        // Trimmed before validating, for the same reason the sign-in screen
        // does it: a pasted address carries whitespace.
        $this->newJudgeName = trim($this->newJudgeName);
        $this->newJudgeEmail = trim($this->newJudgeEmail);
        $this->newJudgeTitle = trim($this->newJudgeTitle);

        $this->validate();

        // Lowercased on write so it can never diverge from the comparison the
        // sign-in screen makes.
        $email = mb_strtolower($this->newJudgeEmail);

        if ($existing = MerdekaJudge::findByEmail($email)) {
            $this->flash = $existing->name.' sudah berada dalam panel dengan e-mel itu.';

            return;
        }

        MerdekaJudge::create([
            'name' => $this->newJudgeName,
            'email' => $email,
            'title' => $this->newJudgeTitle ?: null,
            'sort_order' => (int) MerdekaJudge::max('sort_order') + 1,
        ]);

        $this->reset('newJudgeName', 'newJudgeEmail', 'newJudgeTitle');
        $this->flash = 'Ditambah. Mereka boleh log masuk di /merdeka dengan '.$email.'.';
    }

    public function removeJudge(int $judgeId): void
    {
        $judge = MerdekaJudge::findOrFail($judgeId);

        // Refused once they have filed anything, the same rail the roster puts
        // in front of deleting someone with weigh-ins: removing them would take
        // signed sheets with them and silently move a corner's average.
        if ($judge->scores()->exists()) {
            $this->flash = $judge->name.' telah menghantar markah, jadi mereka tidak boleh dikeluarkan.';

            return;
        }

        $name = $judge->name;
        $judge->delete();

        $this->flash = $name.' telah dikeluarkan dari panel.';
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
        $judges = MerdekaJudge::orderBy('sort_order')->orderBy('id')->get();
        $departments = Department::orderBy('sort_order')->orderBy('name')->get();
        // Judge and department eager-loaded for the detail view; shouldBeStrict
        // is on outside production.
        $scores = MerdekaScore::with(['judge', 'department'])->get();

        /** @var Collection<int, Collection<int, MerdekaScore>> $byDepartment */
        $byDepartment = $scores->groupBy('department_id');

        $rows = $departments->map(function (Department $department) use ($byDepartment, $judges) {
            $filed = $byDepartment->get($department->id, collect());

            return [
                'department' => $department,
                'filed' => $filed->sortBy(fn (MerdekaScore $score) => $judges->search(
                    fn (MerdekaJudge $judge) => $judge->id === $score->merdeka_judge_id
                ))->values(),
                'missing' => $judges->reject(
                    fn (MerdekaJudge $judge) => $filed->contains('merdeka_judge_id', $judge->id)
                )->values(),
                'average' => $filed->isEmpty()
                    ? null
                    : round($filed->sum(fn (MerdekaScore $score) => (float) $score->total) / $filed->count(), 2),
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
            'departmentsComplete' => $rows->filter(
                fn (array $row) => $judges->isNotEmpty() && $row['missing']->isEmpty()
            )->count(),
            'departmentCount' => $departments->count(),
            'openScore' => $this->openScoreId ? $scores->firstWhere('id', $this->openScoreId) : null,
            'criteria' => MerdekaRubric::CRITERIA,
            'scaleLabels' => MerdekaRubric::SCALE_LABELS,
        ]);
    }
}

<?php

namespace App\Livewire\Merdeka;

use App\Models\Department;
use App\Models\MerdekaJudge;
use App\Models\MerdekaScore;
use App\Support\MerdekaRubric;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The panel's scoring screen: pick a corner, mark six criteria, sign, submit.
 *
 * Two rules shape the whole component. Marks are computed here and never read
 * from the browser (DECISIONS.md §4 applies to any derived number). And a
 * submitted sheet is final — the judge signed it, so there is no edit path at
 * all, which is also why nothing here ever loads a signature back onto a canvas.
 */
#[Layout('components.layouts.merdeka')]
#[Title('Borang Markah — Sudut Kemerdekaan 2026')]
class Judging extends Component
{
    /** Null while looking at the list of corners; a department id while scoring one. */
    public ?int $openDepartmentId = null;

    /** criterion id => band 1–5, as chosen. Strings, because they come off radios. */
    public array $scales = [];

    public string $ulasan = '';

    /** PNG data URL, pushed from the signature canvas. */
    public string $signature = '';

    public ?string $flash = null;

    /**
     * The signed-in judge. The `merdeka.judge` middleware guarantees one, and
     * it is re-read per request so a judge removed mid-session stops here.
     */
    private function judge(): MerdekaJudge
    {
        return MerdekaJudge::current() ?? abort(403);
    }

    public function logout(): void
    {
        session()->forget(MerdekaJudge::SESSION_KEY);

        $this->redirectRoute('merdeka.login');
    }

    // ------------------------------------------------------------- navigation

    public function open(int $departmentId): void
    {
        $department = Department::findOrFail($departmentId);

        // Locked on submit. The button is gone from the list, so reaching this
        // means a stale page or a hand-rolled request either way.
        if ($this->submittedTotals()->has($department->id)) {
            $this->flash = 'Markah untuk '.$department->name.' telah dihantar dan tidak boleh diubah.';

            return;
        }

        $this->resetSheet();
        $this->openDepartmentId = $department->id;
    }

    public function back(): void
    {
        $this->resetSheet();
    }

    private function resetSheet(): void
    {
        $this->reset('openDepartmentId', 'scales', 'ulasan', 'signature', 'flash');
    }

    // ----------------------------------------------------------------- submit

    public function submit(): void
    {
        $department = Department::findOrFail($this->openDepartmentId);

        $this->validate($this->rules(), $this->messages());
        $this->assertSignatureIsAPng();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Bands are cast to int here so the stored JSON holds numbers, not the
        // strings the radios produced.
        $judge = $this->judge();

        $scales = collect(MerdekaRubric::ids())
            ->mapWithKeys(fn (string $id) => [$id => (int) $this->scales[$id]])
            ->all();

        // Belt and braces against a double submit: the unique index is the real
        // guard, this is what turns it into a readable message instead of a 500.
        if ($this->submittedTotals()->has($department->id)) {
            $this->flash = 'Markah untuk '.$department->name.' telah pun dihantar.';
            $this->resetSheet();

            return;
        }

        MerdekaScore::create([
            'merdeka_judge_id' => $judge->id,
            'department_id' => $department->id,
            'scales' => $scales,
            'total' => MerdekaRubric::total($scales),
            'ulasan' => trim($this->ulasan) ?: null,
            'signature' => $this->signature,
            'submitted_at' => now(),
        ]);

        $this->resetSheet();
        $this->flash = 'Markah untuk '.$department->name.' telah dihantar. Terima kasih.';
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'ulasan' => 'nullable|string|max:2000',
            // ~450KB of base64. A signature is a few KB; the ceiling is only
            // there so a wide canvas on a hi-dpi tablet can't post something absurd.
            'signature' => ['required', 'string', 'max:600000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/'],
        ];

        foreach (MerdekaRubric::ids() as $id) {
            $rules["scales.{$id}"] = 'required|integer|between:1,5';
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        $messages = [
            'signature.required' => 'Sila tandatangan sebelum menghantar.',
            'signature.regex' => 'Tandatangan tidak sah. Sila padam dan tulis semula.',
            'signature.max' => 'Tandatangan terlalu besar. Sila padam dan tulis semula.',
            'ulasan.max' => 'Ulasan terlalu panjang (maksimum 2000 aksara).',
        ];

        foreach (MerdekaRubric::CRITERIA as $id => $criterion) {
            $messages["scales.{$id}.required"] = 'Sila pilih skala bagi "'.$criterion['name'].'".';
            $messages["scales.{$id}.between"] = 'Skala bagi "'.$criterion['name'].'" mesti antara 1 hingga 5.';
        }

        return $messages;
    }

    /**
     * The regex proves the shape of the string; this proves it is actually a
     * PNG, so a well-formed but junk payload can't be stored and then fail to
     * render on the results screen weeks later.
     */
    private function assertSignatureIsAPng(): void
    {
        $binary = base64_decode(substr($this->signature, strlen('data:image/png;base64,')), strict: true);

        if ($binary === false || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            $this->addError('signature', 'Tandatangan tidak sah. Sila padam dan tulis semula.');
        }
    }

    // ------------------------------------------------------------------- data

    /** This judge's own filed sheets: department_id => total. Never anyone else's. */
    private function submittedTotals(): Collection
    {
        return MerdekaScore::where('merdeka_judge_id', $this->judge()->id)
            ->pluck('total', 'department_id');
    }

    public function render()
    {
        $submitted = $this->submittedTotals();
        $departments = Department::orderBy('sort_order')->orderBy('name')->get();

        return view('livewire.merdeka.judging', [
            'departments' => $departments,
            'submitted' => $submitted,
            'doneCount' => $departments->filter(fn ($d) => $submitted->has($d->id))->count(),
            'openDepartment' => $this->openDepartmentId ? $departments->firstWhere('id', $this->openDepartmentId) : null,
            'criteria' => MerdekaRubric::CRITERIA,
            'bands' => MerdekaRubric::BANDS,
            'scaleLabels' => MerdekaRubric::SCALE_LABELS,
            // Recomputed server-side on every band change — what the judge sees
            // is the same number that will be stored.
            'runningTotal' => MerdekaRubric::total($this->scales),
            // Drives the submit button alongside the signature. Validation is
            // still the real check; this only avoids offering a dead button.
            'allBandsChosen' => collect(MerdekaRubric::ids())
                ->every(fn (string $id) => filled($this->scales[$id] ?? null)),
            'judge' => $this->judge(),
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Services\RosterImporter;
use App\Support\ImportRow;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk roster load from a CSV.
 *
 * Two steps on purpose: upload and preview, then commit. Forty rows that half
 * apply are worse than forty that refuse, so nothing is written until every row
 * parses cleanly and the admin has seen what will happen.
 *
 * Parsed rows are deliberately NOT held in a public property. Livewire
 * serialises public state between requests and ImportRow is a plain object that
 * wouldn't survive the round trip — so the upload is the only thing kept, and
 * the rows are re-derived from it each request.
 */
#[Layout('components.layouts.app')]
#[Title('Import roster — BMI Challenge')]
class RosterImport extends Component
{
    use WithFileUploads;

    public $file;

    public ?string $error = null;

    public ?string $done = null;

    /** Per-request memo, never serialised. */
    private ?Collection $parsed = null;

    public function updatedFile(): void
    {
        $this->reset(['error', 'done']);
        $this->parsed = null;

        $this->validate([
            'file' => ['required', 'file', 'max:2048'],
        ]);

        // Spreadsheets are commonly served as text/plain, so the extension is a
        // more reliable check than the mime type.
        if (! in_array(strtolower($this->file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            $this->error = 'That needs to be a .csv file. In Google Sheets: File → Download → Comma-separated values.';
            $this->file = null;
        }
    }

    /** @return Collection<int,ImportRow> */
    public function rows(): Collection
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        if (! $this->file || $this->error) {
            return $this->parsed = collect();
        }

        try {
            return $this->parsed = app(RosterImporter::class)
                ->parse(file_get_contents($this->file->getRealPath()));
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return $this->parsed = collect();
        }
    }

    public function commit(RosterImporter $importer): void
    {
        $rows = $this->rows();

        if ($rows->isEmpty()) {
            return;
        }

        try {
            $result = $importer->apply($rows);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->done = sprintf(
            '%d added, %d updated. Nobody was removed — leaving the challenge is a separate action.',
            $result['created'],
            $result['updated'],
        );

        $this->reset(['file', 'error']);
        $this->parsed = null;
    }

    public function render()
    {
        $rows = $this->rows();

        return view('livewire.roster-import', [
            'rows' => $rows,
            'createCount' => $rows->where('action', ImportRow::CREATE)->count(),
            'updateCount' => $rows->where('action', ImportRow::UPDATE)->count(),
            'errorCount' => $rows->where('action', ImportRow::ERROR)->count(),
        ]);
    }
}

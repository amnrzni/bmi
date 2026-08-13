<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Support\ImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk roster load from a CSV exported out of Google Sheets.
 *
 * Parsing, validation and applying all live here rather than in the Livewire
 * component, so the rules can be tested without a browser.
 *
 * Two things this deliberately never does:
 *   - write anything while any row is in error (a half-applied roster is worse
 *     than a refused one)
 *   - treat absence from the file as removal (leaving is an explicit action)
 */
class RosterImporter
{
    /**
     * Accepted header spellings. Sheets in the wild are in Malay or English and
     * nobody should have to reshape a spreadsheet to import it.
     *
     * @var array<string,array<int,string>>
     */
    private const COLUMNS = [
        'name' => ['name', 'nama', 'staff', 'full name', 'fullname', 'staff name'],
        'email' => ['email', 'e-mail', 'emel', 'email address'],
        'department' => ['department', 'dept', 'jabatan', 'unit'],
        'team' => ['team', 'pasukan'],
        'height_cm' => ['height', 'height_cm', 'height (cm)', 'tinggi'],
        'joined_at' => ['joined', 'joined_at', 'join date', 'tarikh masuk'],
    ];

    /**
     * @return Collection<int,ImportRow>
     *
     * @throws \RuntimeException when the file has no usable header
     */
    public function parse(string $contents): Collection
    {
        $lines = $this->toLines($contents);

        if ($lines === []) {
            throw new \RuntimeException('That file is empty.');
        }

        $map = $this->mapHeader(array_shift($lines));

        foreach (['name', 'email'] as $required) {
            if (! isset($map[$required])) {
                throw new \RuntimeException(
                    "The file needs a \"{$required}\" column. Found: ".
                    implode(', ', array_map(fn ($c) => '"'.$c.'"', $map['_headers'] ?? [])).'.'
                );
            }
        }

        $rows = collect();
        $seenEmails = [];

        foreach ($lines as $index => $line) {
            // Explicit empty escape: RFC 4180 behaviour, which is what
            // spreadsheets emit, and avoids the PHP 8.4 deprecation on the
            // implicit escape character.
            $cells = str_getcsv($line, ',', '"', '');

            // Skip trailing blank lines rather than reporting them as errors.
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $values = [];
            foreach ($map as $field => $position) {
                if ($field === '_headers') {
                    continue;
                }
                $values[$field] = $cells[$position] ?? null;
            }

            $rows->push($this->validateRow(
                new ImportRow(line: $index + 2, values: $values),
                $seenEmails,
            ));
        }

        if ($rows->isEmpty()) {
            throw new \RuntimeException('That file has a header but no rows.');
        }

        return $rows;
    }

    /**
     * @param  Collection<int,ImportRow>  $rows
     * @return array{created: int, updated: int}
     */
    public function apply(Collection $rows): array
    {
        if ($rows->contains(fn (ImportRow $row) => $row->isError())) {
            throw new \RuntimeException('Fix the errors before importing.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $attributes = [
                    'name' => $row->value('name'),
                    // Stored lowercase: sign-in compares case-insensitively, so
                    // normalising here stops two rows that look different from
                    // colliding at the point of sign-in.
                    'email' => mb_strtolower($row->value('email')),
                    'department_id' => $this->departmentId($row->value('department')),
                    'team_id' => $this->teamId($row->value('team')),
                    'height_cm' => $row->value('height_cm') ? (int) $row->value('height_cm') : null,
                ];

                if ($joined = $row->value('joined_at')) {
                    $attributes['joined_at'] = $joined;
                }

                if ($row->existing) {
                    $row->existing->update($attributes);
                    $updated++;
                } else {
                    User::create([...$attributes, 'is_participant' => true]);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    // ----------------------------------------------------------------- parsing

    /**
     * Google Sheets exports CRLF line endings and a UTF-8 BOM. The BOM silently
     * corrupts the first header cell, so "name" stops matching and the file
     * looks broken for no visible reason.
     *
     * @return array<int,string>
     */
    private function toLines(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        return array_values(array_filter(
            explode("\n", trim($contents)),
            fn (string $line) => trim($line) !== '',
        ));
    }

    /** @return array<string,int|array<int,string>> */
    private function mapHeader(string $header): array
    {
        $headers = array_map(
            fn ($cell) => mb_strtolower(trim((string) $cell)),
            str_getcsv($header, ',', '"', ''),
        );

        $map = ['_headers' => $headers];

        foreach (self::COLUMNS as $field => $aliases) {
            foreach ($headers as $position => $heading) {
                if (in_array($heading, $aliases, true)) {
                    $map[$field] = $position;
                    break;
                }
            }
        }

        return $map;
    }

    /** @param  array<string,int>  $seenEmails */
    private function validateRow(ImportRow $row, array &$seenEmails): ImportRow
    {
        if (! $row->value('name')) {
            $row->fail('Name is missing.');
        }

        $email = $row->value('email');

        if (! $email) {
            $row->fail('Email is missing.');
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $row->fail("\"{$email}\" isn't a valid email address.");
        } else {
            $key = mb_strtolower($email);

            if (isset($seenEmails[$key])) {
                $row->fail("Duplicate of line {$seenEmails[$key]} in this file.");
            } else {
                $seenEmails[$key] = $row->line;

                // Matched the same way sign-in matches, so what imports is what
                // can log in.
                $row->existing = User::withTrashed()->whereRaw('lower(email) = ?', [$key])->first();

                if ($row->existing && ! $row->isError()) {
                    $row->action = ImportRow::UPDATE;
                }
            }
        }

        if ($height = $row->value('height_cm')) {
            if (! is_numeric($height) || (int) $height < 100 || (int) $height > 250) {
                $row->fail("Height \"{$height}\" should be between 100 and 250 cm.");
            }
        }

        if ($team = $row->value('team')) {
            // Teams are fixed this cycle; a typo here would silently create a
            // third team and quietly corrupt the standings.
            if (! $this->teamId($team)) {
                $known = Team::orderBy('sort_order')->pluck('code')->implode(', ');
                $row->fail("Unknown team \"{$team}\". Use one of: {$known}.");
            }
        }

        return $row;
    }

    /** Departments are created on demand — unlike teams, they're open-ended. */
    private function departmentId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return Department::firstOrCreate(
            ['name' => $name],
            ['sort_order' => Department::max('sort_order') + 1],
        )->id;
    }

    private function teamId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return Team::whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])
            ->orWhereRaw('lower(name) = ?', [mb_strtolower(trim($code))])
            ->value('id');
    }
}

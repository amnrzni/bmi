<?php

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Models\WeighIn;
use App\Services\RosterImporter;
use App\Support\ImportRow;

beforeEach(function () {
    $this->tah = Team::factory()->create(['code' => 'TAH', 'name' => 'Team TAH', 'sort_order' => 1]);
    $this->bkn = Team::factory()->create(['code' => 'BKN', 'name' => 'Team BKN', 'sort_order' => 2]);
    $this->importer = app(RosterImporter::class);
});

function csv(string $body): string
{
    return "name,email,department,team,height\n".$body;
}

// -------------------------------------------------------------------- parsing

it('creates people from a simple sheet export', function () {
    $rows = $this->importer->parse(csv(
        "Along,along@qcxis.com,Operations,TAH,172\n".
        "Kuale,kuale@qcxis.com,Operations,BKN,165\n"
    ));

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (ImportRow $r) => $r->action === ImportRow::CREATE))->toBeTrue();

    expect($this->importer->apply($rows))->toBe(['created' => 2, 'updated' => 0]);

    $along = User::whereRaw('lower(email) = ?', ['along@qcxis.com'])->first();

    expect($along->name)->toBe('Along')
        ->and($along->height_cm)->toBe(172)
        ->and($along->team->code)->toBe('TAH')
        ->and($along->department->name)->toBe('Operations')
        ->and($along->is_participant)->toBeTrue();
});

it('survives the BOM and CRLF that google sheets exports', function () {
    // A BOM corrupts the first header cell, so "name" stops matching and the
    // whole file looks broken for no visible reason.
    $contents = "\xEF\xBB\xBFname,email,team\r\nAlong,along@qcxis.com,TAH\r\n";

    $rows = $this->importer->parse($contents);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->isError())->toBeFalse()
        ->and($rows->first()->value('name'))->toBe('Along');
});

it('accepts malay column headings', function () {
    $rows = $this->importer->parse("nama,emel,jabatan,pasukan,tinggi\nAlong,along@qcxis.com,Operasi,TAH,172\n");

    expect($rows->first()->isError())->toBeFalse()
        ->and($rows->first()->value('name'))->toBe('Along');
});

it('ignores blank trailing lines', function () {
    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Ops,TAH,172\n,,,,\n\n"));

    expect($rows)->toHaveCount(1);
});

it('refuses a file with no name or email column', function () {
    expect(fn () => $this->importer->parse("weight,team\n80,TAH\n"))
        ->toThrow(RuntimeException::class);
});

it('refuses an empty file', function () {
    expect(fn () => $this->importer->parse(''))->toThrow(RuntimeException::class);
    expect(fn () => $this->importer->parse(csv('')))->toThrow(RuntimeException::class);
});

// ----------------------------------------------------------------- matching

it('updates an existing person matched on email, ignoring case', function () {
    $existing = User::factory()->create(['email' => 'along@qcxis.com', 'name' => 'Old Name']);

    $rows = $this->importer->parse(csv("Along,ALONG@QCXIS.COM,Operations,TAH,172\n"));

    expect($rows->first()->action)->toBe(ImportRow::UPDATE);

    expect($this->importer->apply($rows))->toBe(['created' => 0, 'updated' => 1]);

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Along');
});

it('stores emails lowercased so they can never collide at sign-in', function () {
    $rows = $this->importer->parse(csv("Along,ALong@QCXIS.com,Ops,TAH,172\n"));
    $this->importer->apply($rows);

    expect(User::first()->email)->toBe('along@qcxis.com');
});

it('creates departments it has not seen, but never teams', function () {
    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Brand New Dept,TAH,172\n"));
    $this->importer->apply($rows);

    expect(Department::where('name', 'Brand New Dept')->exists())->toBeTrue()
        ->and(Team::count())->toBe(2);
});

it('errors on an unknown team rather than inventing one', function () {
    // A typo here would silently create a third team and corrupt the standings.
    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Ops,TAHH,172\n"));

    expect($rows->first()->isError())->toBeTrue()
        ->and($rows->first()->errorText())->toContain('Unknown team')
        ->and($rows->first()->errorText())->toContain('TAH');
});

// --------------------------------------------------------------- validation

it('errors on a duplicate email inside the same file', function () {
    $rows = $this->importer->parse(csv(
        "Along,along@qcxis.com,Ops,TAH,172\n".
        "Along Again,ALONG@qcxis.com,Ops,BKN,171\n"
    ));

    expect($rows[0]->isError())->toBeFalse()
        ->and($rows[1]->isError())->toBeTrue()
        ->and($rows[1]->errorText())->toContain('Duplicate of line 2');
});

it('errors on a missing name, a bad email and an implausible height', function () {
    $rows = $this->importer->parse(csv(
        ",noname@qcxis.com,Ops,TAH,172\n".
        "Bad Email,not-an-email,Ops,TAH,172\n".
        "Tall,tall@qcxis.com,Ops,TAH,900\n"
    ));

    expect($rows[0]->errorText())->toContain('Name is missing')
        ->and($rows[1]->errorText())->toContain("isn't a valid email")
        ->and($rows[2]->errorText())->toContain('between 100 and 250');
});

it('allows a blank height, which only blocks BMI', function () {
    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Ops,TAH,\n"));
    $this->importer->apply($rows);

    expect($rows->first()->isError())->toBeFalse()
        ->and(User::first()->height_cm)->toBeNull();
});

// ------------------------------------------------------------------ applying

it('writes nothing at all when any row is in error', function () {
    $rows = $this->importer->parse(csv(
        "Good,good@qcxis.com,Ops,TAH,172\n".
        "Bad,bad@qcxis.com,Ops,NOPE,172\n"
    ));

    // A half-applied roster is worse than a refused one.
    expect(fn () => $this->importer->apply($rows))->toThrow(RuntimeException::class);

    expect(User::count())->toBe(0);
});

it('never removes anybody who is absent from the file', function () {
    $untouched = User::factory()->create(['email' => 'staying@qcxis.com']);

    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Ops,TAH,172\n"));
    $this->importer->apply($rows);

    // Leaving the challenge is an explicit action, never a side effect of import.
    expect($untouched->fresh())->not->toBeNull()
        ->and($untouched->fresh()->left_at)->toBeNull()
        ->and(User::count())->toBe(2);
});

it('keeps weigh-in history when it updates someone', function () {
    $existing = User::factory()->create(['email' => 'along@qcxis.com', 'height_cm' => 170]);
    WeighIn::factory()->create([
        'user_id' => $existing->id,
        'recorded_by_user_id' => User::factory()->admin()->create()->id,
    ]);

    $rows = $this->importer->parse(csv("Along,along@qcxis.com,Ops,TAH,175\n"));
    $this->importer->apply($rows);

    expect($existing->fresh()->weighIns()->count())->toBe(1)
        ->and($existing->fresh()->height_cm)->toBe(175);
});

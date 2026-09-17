<?php

use App\Enums\Division;
use App\Models\TournamentFinal;
use App\Models\TournamentScore;
use App\Models\User;
use App\Services\TournamentRecorder;
use App\Support\TournamentBoard;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->recorder = app(TournamentRecorder::class);

    // Record a group score by match number.
    $this->score = function ($tournament, int $number, int $home, int $away, Division $division = Division::Men) {
        $fixture = $tournament->fixtures()->where('number', $number)->sole();

        return $this->recorder->recordScore($fixture, $division, $home, $away, $this->admin);
    };

    $this->final = fn ($tournament, int $home, int $away, ?int $homePens = null, ?int $awayPens = null, Division $division = Division::Men) => $this->recorder->recordFinal($tournament, $division, $home, $away, $homePens, $awayPens, $this->admin);

    $this->table = fn ($tournament, Division $division = Division::Men) => TournamentBoard::for($tournament)
        ->standings($division)
        ->map(fn ($line) => $line->team->name)
        ->all();
});

// --------------------------------------------------------------- standings

it('awards three for a win, one for a draw, and ignores unplayed matches', function () {
    // 1: Alpha–Bravo, 2: Alpha–Charlie, 3: Bravo–Charlie
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie']);
    ($this->score)($tournament, 1, 3, 1);
    ($this->score)($tournament, 2, 2, 2);

    $lines = TournamentBoard::for($tournament)->standings(Division::Men)->keyBy(fn ($line) => $line->team->name);

    expect($lines['Alpha']->played())->toBe(2)
        ->and($lines['Alpha']->won)->toBe(1)
        ->and($lines['Alpha']->drawn)->toBe(1)
        ->and($lines['Alpha']->points())->toBe(4)
        ->and($lines['Alpha']->scoredFor)->toBe(5)
        ->and($lines['Alpha']->scoredAgainst)->toBe(3)
        ->and($lines['Alpha']->difference())->toBe(2)
        ->and($lines['Bravo']->lost)->toBe(1)
        ->and($lines['Bravo']->points())->toBe(0)
        ->and($lines['Charlie']->played())->toBe(1)
        ->and($lines['Charlie']->points())->toBe(1)
        ->and(($this->table)($tournament))->toBe(['Alpha', 'Charlie', 'Bravo']);
});

it('breaks a points tie on score difference', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie', 'Delta'], [['Alpha', 'Bravo'], ['Charlie', 'Delta']]);
    ($this->score)($tournament, 1, 2, 1);   // Alpha +1
    ($this->score)($tournament, 2, 5, 0);   // Charlie +5

    expect(($this->table)($tournament))->toBe(['Charlie', 'Alpha', 'Bravo', 'Delta']);
});

it('breaks a points and difference tie on scored', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie', 'Delta'], [['Alpha', 'Bravo'], ['Charlie', 'Delta']]);
    ($this->score)($tournament, 1, 3, 1);   // Alpha +2, scored 3
    ($this->score)($tournament, 2, 4, 2);   // Charlie +2, scored 4

    expect(($this->table)($tournament))->toBe(['Charlie', 'Alpha', 'Delta', 'Bravo']);
});

it('falls back to list order when everything is level', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie', 'Delta'], [['Alpha', 'Bravo'], ['Charlie', 'Delta']]);

    expect(($this->table)($tournament))->toBe(['Alpha', 'Bravo', 'Charlie', 'Delta']);

    ($this->score)($tournament, 1, 2, 1);
    ($this->score)($tournament, 2, 2, 1);

    expect(($this->table)($tournament))->toBe(['Alpha', 'Charlie', 'Bravo', 'Delta']);
});

it('keeps the divisions apart and adds them together for the combined table', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 2, 0, Division::Men);
    ($this->score)($tournament, 1, 0, 1, Division::Women);

    $combined = TournamentBoard::for($tournament)->combined();

    expect(($this->table)($tournament, Division::Men))->toBe(['Alpha', 'Bravo'])
        ->and(($this->table)($tournament, Division::Women))->toBe(['Bravo', 'Alpha'])
        ->and($combined->map(fn ($line) => $line->team->name)->all())->toBe(['Alpha', 'Bravo'])
        ->and($combined[0]->played())->toBe(2)
        ->and($combined[0]->points())->toBe(3)
        ->and($combined[0]->difference())->toBe(1);
});

// ------------------------------------------------------------------- final

it('opens the final only once every group match in the division has a score', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie']);
    ($this->score)($tournament, 1, 1, 0);
    ($this->score)($tournament, 2, 1, 0);

    expect(TournamentBoard::for($tournament)->seededFinalists(Division::Men))->toBeNull()
        ->and(fn () => ($this->final)($tournament, 2, 1))->toThrow(InvalidArgumentException::class);

    ($this->score)($tournament, 3, 1, 0);
    $board = TournamentBoard::for($tournament);

    expect($board->groupStageComplete(Division::Men))->toBeTrue()
        ->and($board->groupStageComplete(Division::Women))->toBeFalse()
        ->and(array_map(fn ($team) => $team->name, $board->seededFinalists(Division::Men)))->toBe(['Alpha', 'Bravo']);
});

it('has no group stage to complete without fixtures', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo'], []);

    expect(TournamentBoard::for($tournament)->groupStageComplete(Division::Men))->toBeFalse();
});

it('locks the finalists when the final is saved, and flags a later group correction', function () {
    // 1: Alpha–Bravo, 2: Alpha–Charlie, 3: Bravo–Charlie
    $tournament = tournamentWith(['Alpha', 'Bravo', 'Charlie']);
    ($this->score)($tournament, 1, 1, 0);
    ($this->score)($tournament, 2, 1, 0);
    ($this->score)($tournament, 3, 1, 0);

    $final = ($this->final)($tournament, 3, 1);
    $names = fn () => [
        TournamentBoard::for($tournament)->team($final->fresh()->home_team_id)->name,
        TournamentBoard::for($tournament)->team($final->fresh()->away_team_id)->name,
    ];

    expect($names())->toBe(['Alpha', 'Bravo'])
        ->and(TournamentBoard::for($tournament)->finalistsOutOfDate(Division::Men))->toBeFalse();

    // Match 3 was typed backwards: Charlie won it, so Charlie is now second.
    ($this->score)($tournament, 3, 0, 5);
    ($this->final)($tournament, 4, 1);

    expect($names())->toBe(['Alpha', 'Bravo'])
        ->and($final->fresh()->home_score)->toBe(4)
        ->and(TournamentBoard::for($tournament)->finalistsOutOfDate(Division::Men))->toBeTrue();

    $this->recorder->clearFinal($tournament, Division::Men);
    $reseeded = ($this->final)($tournament, 2, 0);
    $board = TournamentBoard::for($tournament);

    expect([$board->team($reseeded->home_team_id)->name, $board->team($reseeded->away_team_id)->name])->toBe(['Alpha', 'Charlie'])
        ->and($board->finalistsOutOfDate(Division::Men))->toBeFalse();
});

it('needs a decisive penalty score when the final is level', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 1, 0);

    expect(fn () => ($this->final)($tournament, 2, 2))->toThrow(InvalidArgumentException::class, 'penalty score')
        ->and(fn () => ($this->final)($tournament, 2, 2, 3, 3))->toThrow(InvalidArgumentException::class, "can't be level");

    $final = ($this->final)($tournament, 2, 2, 3, 4);

    expect($final->wentToPenalties())->toBeTrue()
        ->and($final->winnerTeamId())->toBe($final->away_team_id);
});

it('drops penalties from a final that was decided in play', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 1, 0);

    $final = ($this->final)($tournament, 1, 3, 5, 4);

    expect($final->fresh()->home_penalties)->toBeNull()
        ->and($final->fresh()->away_penalties)->toBeNull()
        ->and($final->winnerTeamId())->toBe($final->away_team_id);
});

it('keeps one final per division', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 1, 0, Division::Men);
    ($this->score)($tournament, 1, 1, 0, Division::Women);

    ($this->final)($tournament, 1, 0, division: Division::Men);
    ($this->final)($tournament, 0, 1, division: Division::Women);
    ($this->final)($tournament, 2, 0, division: Division::Men);

    expect(TournamentFinal::count())->toBe(2);
});

// ------------------------------------------------------------------ writes

it('corrects a score in place and clears it', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 1, 0);
    ($this->score)($tournament, 1, 0, 1);

    expect(TournamentScore::sole()->away_score)->toBe(1);

    $this->recorder->clearScore($tournament->fixtures()->sole(), Division::Men);

    expect(TournamentScore::count())->toBe(0);
});

it('rejects a score outside 0 to 999', function (int $bad) {
    $tournament = tournamentWith(['Alpha', 'Bravo']);

    expect(fn () => ($this->score)($tournament, 1, $bad, 0))->toThrow(InvalidArgumentException::class);
})->with([-1, 1000]);

it('writes every score change to the activity log', function () {
    $tournament = tournamentWith(['Alpha', 'Bravo']);
    ($this->score)($tournament, 1, 1, 0);
    ($this->score)($tournament, 1, 2, 0);
    $this->recorder->clearScore($tournament->fixtures()->sole(), Division::Men);

    expect(DB::table('activity_log')->where('subject_type', TournamentScore::class)->count())->toBe(3);
});

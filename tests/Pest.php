<?php

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A tournament with named teams (in list order) and group fixtures between
 * them — every pairing once, unless pairs are given.
 *
 * @param  list<string>  $teams
 * @param  list<array{0: string, 1: string}>|null  $pairs
 */
function tournamentWith(array $teams = ['Alpha', 'Bravo', 'Charlie'], ?array $pairs = null): Tournament
{
    $tournament = Tournament::create(['name' => 'Test Cup', 'slug' => Tournament::slugFor('Test Cup')]);

    $made = collect($teams)
        ->mapWithKeys(fn (string $name, int $i) => [$name => $tournament->teams()->create(['name' => $name, 'sort_order' => $i + 1])]);

    if ($pairs === null) {
        $pairs = [];
        foreach ($teams as $i => $home) {
            foreach (array_slice($teams, $i + 1) as $away) {
                $pairs[] = [$home, $away];
            }
        }
    }

    foreach ($pairs as $i => [$home, $away]) {
        $tournament->fixtures()->create([
            'number' => $i + 1,
            'home_team_id' => $made[$home]->id,
            'away_team_id' => $made[$away]->id,
        ]);
    }

    return $tournament;
}

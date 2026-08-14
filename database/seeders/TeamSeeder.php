<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * The only seeder that is safe to run in production.
 *
 * Teams have to exist before anyone can be assigned or imported, but the other
 * seeders create ~37 fictional staff. Keeping the two apart means production
 * can be given its teams without the demo roster coming along.
 *
 *   php artisan db:seed --class=TeamSeeder --force
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        // Generic placeholders for a fresh install. The admin renames these on
        // the roster screen — the names aren't the app's to decide.
        for ($i = 1; $i <= 8; $i++) {
            Team::updateOrCreate(
                ['code' => 'TM'.$i],
                ['name' => 'Team '.$i, 'sort_order' => $i],
            );
        }
    }
}

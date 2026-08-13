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
        foreach ([
            ['code' => 'TAH', 'name' => 'Team TAH', 'sort_order' => 1],
            ['code' => 'BKN', 'name' => 'Team BKN', 'sort_order' => 2],
        ] as $attributes) {
            Team::updateOrCreate(['code' => $attributes['code']], $attributes);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RosterSeeder::class,
            DemoWeighInSeeder::class,
            DemoEventSeeder::class,
        ]);
    }
}

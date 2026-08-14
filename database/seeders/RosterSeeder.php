<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Placeholder roster so every screen can be demoed before the real one lands.
 *
 * Department names and staff are invented — replace them by typing the real
 * roster into the admin screen, or by importing the Google Sheet once its
 * columns are known.
 */
class RosterSeeder extends Seeder
{
    /** Departments, and the staff in each as [name, height_cm]. */
    private const ROSTER = [
        'Academic' => [
            ['Farah', 160], ['Hakim', 175], ['Nurul', 158],
            ['Zaidi', 170], ['Aina', 162], ['Rahman', 178],
        ],
        'Operations' => [
            ['Along', 172], ['Dragon', 180], ['Kuale', 165], ['Yong', 168],
            ['Ecah', 157], ['Bonda', 163], ['Ayahanda', 176], ['Lengchai', 174],
        ],
        'Marketing' => [
            ['Suria', 161], ['Faiz', 177], ['Mira', 159], ['Danial', 171], ['Ilya', 164],
        ],
        'Finance' => [
            ['Aziz', 169], ['Liyana', 160], ['Tan', 173], ['Siva', 175],
        ],
        'IT Support' => [
            ['Zen', 172], ['Akmal', 170], ['Aqil', 168], ['Pian', 174],
            ['Wan', 166], ['Haziq', 179], ['Nabil', 171],
        ],
        'Customer Service' => [
            ['Sofia', 158], ['Amir', 176], ['Qistina', 162], ['Raju', 170],
            ['Elle', 160], ['Fikri', 173], ['Muna', 159],
        ],
    ];

    public function run(): void
    {
        // Teams live in their own seeder so production can create them without
        // dragging in the demo staff below.
        $this->callSilent(TeamSeeder::class);

        $teams = Team::orderBy('sort_order')->get();

        // The admin who runs the challenge — and competes in it.
        $admin = User::updateOrCreate(
            ['email' => 'dev@qcxis.com'],
            [
                'name' => 'ZEN',
                'role' => Role::Admin,
                'password' => Hash::make('password'),
                'is_participant' => true,
                'height_cm' => 172,
                'consented_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        $index = 0;

        foreach (self::ROSTER as $departmentName => $staff) {
            $department = Department::updateOrCreate(
                ['name' => $departmentName],
                ['sort_order' => $index],
            );

            foreach ($staff as [$name, $heightCm]) {
                // Round-robin across all teams so departments split across them —
                // team assignment is per-individual, never per-department.
                $team = $teams[$index % $teams->count()];

                User::updateOrCreate(
                    ['email' => Str::slug($name).'@example.test'],
                    [
                        'name' => $name,
                        'role' => Role::Staff,
                        'is_participant' => true,
                        'department_id' => $department->id,
                        'team_id' => $team->id,
                        'height_cm' => $heightCm,
                        'consented_at' => now(),
                        'email_verified_at' => now(),
                    ],
                );

                $index++;
            }
        }

        // Put the admin in a department and a team too.
        $admin->update([
            'department_id' => Department::where('name', 'IT Support')->value('id'),
            'team_id' => $teams[0]->id,
        ]);

        // A couple left unassigned on purpose, so the roster screen shows that state.
        User::whereIn('email', ['muna@example.test', 'lengchai@example.test'])
            ->update(['team_id' => null]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Put the seven competing departments on the roster's departments table, which
 * is what the Merdeka judging screens list.
 *
 * Additive only. The seeded placeholder departments are left where they are
 * with their staff attached, and reported rather than removed — deleting one
 * would orphan real people's org identity to tidy up a contest screen.
 */
class MerdekaDepartments extends Command
{
    protected $signature = 'merdeka:departments {--dry-run : Report what would change without writing}';

    protected $description = 'Add the Merdeka contest departments and report any others';

    /** In judging order. Matched on name, so a rename here creates a duplicate. */
    private const COMPETING = [
        'Resource Management',
        'Marketing',
        'Logistic',
        'Franchise & Operation',
        'Research, Development & Education',
        'QCXIS (Subsidiary Company)',
        'Qulhaq Consultancy (Subsidiary Company)',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->components->info($dryRun ? 'Dry run — nothing will be written.' : 'Syncing contest departments.');

        foreach (self::COMPETING as $index => $name) {
            $existing = Department::where('name', $name)->first();
            $order = $index + 1;

            if ($existing && $existing->sort_order === $order) {
                $this->components->twoColumnDetail($name, '<fg=gray>already correct</>');

                continue;
            }

            if (! $dryRun) {
                Department::updateOrCreate(['name' => $name], ['sort_order' => $order]);
            }

            $this->components->twoColumnDetail(
                $name,
                $existing ? '<fg=yellow>reordered to '.$order.'</>' : '<fg=green>created</>',
            );
        }

        // Ordering the seven also reorders the roster screen, which reads the
        // same column. That's the intended result, but say so rather than
        // letting an admin discover it.
        $this->newLine();
        $this->line('  The roster screen orders departments by the same column, so it now follows contest order.');

        $others = Department::whereNotIn('name', self::COMPETING)->orderBy('name')->get();

        if ($others->isEmpty()) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->warn($others->count().' other department(s) exist and will appear on the judging screen:');

        foreach ($others as $department) {
            $staff = User::where('department_id', $department->id)->count();

            $this->components->twoColumnDetail(
                '  '.$department->name,
                $staff === 0 ? '<fg=gray>no staff</>' : $staff.' staff attached',
            );
        }

        $this->newLine();
        $this->line('  These are the seeded placeholders. Move their staff to a real department on the');
        $this->line('  roster screen first, then delete them there — this command never deletes.');

        return self::SUCCESS;
    }
}

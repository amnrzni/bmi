<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Read-only view of the roster from the command line.
 *
 * Exists so diagnosing a sign-in problem on a server doesn't mean opening
 * tinker or a database GUI. The column that matters most is "signed in":
 * because SSO matches on email alone, someone who never signs in almost always
 * has an email that doesn't match their QCXIS account.
 */
class ListRoster extends Command
{
    protected $signature = 'challenge:roster
        {--admins : Only admins}
        {--missing : Only people who have never signed in}';

    protected $description = 'List the roster with sign-in status';

    public function handle(): int
    {
        $users = User::query()
            ->with(['team', 'department'])
            ->when($this->option('admins'), fn ($query) => $query->admins())
            ->when($this->option('missing'), fn ($query) => $query->whereNull('last_login_at'))
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('No matching accounts. Add one with: php artisan challenge:admin <email>');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Email (SSO match key)', 'Role', 'Dept', 'Team', 'Signed in'],
            $users->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->role->value,
                $user->department?->name ?? '—',
                $user->team?->code ?? '—',
                $user->last_login_at?->format('j M H:i') ?? 'never',
            ])->all(),
        );

        $never = $users->filter->hasNeverSignedIn()->count();

        $this->line("  {$users->count()} shown · {$never} never signed in");

        if ($never > 0) {
            $this->newLine();
            $this->warn('  "never" usually means the email here does not match their QCXIS account.');
        }

        return self::SUCCESS;
    }
}

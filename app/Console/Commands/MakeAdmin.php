<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Create or promote an admin by email.
 *
 * The email matters more than it looks: SSO matches the QCXIS identity to a
 * roster row on email alone. An admin whose email doesn't match their QCXIS
 * account will be rejected at sign-in as "not on the challenge roster".
 */
class MakeAdmin extends Command
{
    protected $signature = 'challenge:admin
        {email : Must match their QCXIS account email exactly}
        {--name= : Display name (defaults to the email local part)}
        {--password= : Sets a password for the admin fallback login}
        {--no-compete : Runs the challenge without taking part in it}';

    protected $description = 'Create or promote an admin account';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        $existing = User::withTrashed()->whereRaw('lower(email) = ?', [$email])->first();

        $attributes = [
            'role' => Role::Admin,
            'email_verified_at' => now(),
            // Admins running the challenge shouldn't hit the consent gate on
            // their way to the roster screen.
            'consented_at' => now(),
        ];

        if ($this->option('no-compete')) {
            $attributes['is_participant'] = false;
        }

        if ($password = $this->option('password')) {
            $attributes['password'] = Hash::make($password);
        }

        if ($existing) {
            $existing->restore();
            $existing->update($attributes);

            $this->info("Promoted {$existing->name} <{$email}> to admin.");
        } else {
            $existing = User::create([
                ...$attributes,
                'email' => $email,
                'name' => $this->option('name') ?: str($email)->before('@')->headline(),
            ]);

            $this->info("Created admin {$existing->name} <{$email}>.");
        }

        // Re-read: a freshly created model doesn't carry database defaults such
        // as is_participant, so reporting from it would misstate the row.
        $existing->refresh();

        $this->newLine();
        $this->line('  Sign in with QCXIS: '.($existing->email));
        $this->line('  Fallback password:  '.($existing->password ? 'set' : 'not set — pass --password to enable'));
        $this->line('  Competing:          '.($existing->is_participant ? 'yes' : 'no'));

        if (! $existing->height_cm && $existing->is_participant) {
            $this->newLine();
            $this->warn('  No height on file — BMI can\'t be shown until one is set on the roster screen.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Default: a consented staff participant with a height, since that's the
     * shape almost every test and seed needs.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => null, // SSO users have no local password
            // Present so strict mode doesn't trip when the auth guard reads it.
            'remember_token' => null,
            'role' => Role::Staff,
            'is_participant' => true,
            'department_id' => null,
            'team_id' => null,
            'height_cm' => fake()->numberBetween(150, 185),
            'joined_at' => null,
            'left_at' => null,
            'consented_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => Role::Admin,
            'password' => static::$password ??= Hash::make('password'),
        ]);
    }

    /** Hasn't passed the consent gate yet. */
    public function unconsented(): static
    {
        return $this->state(fn () => ['consented_at' => null]);
    }

    /** Height never captured — BMI is impossible, weight tracking still works. */
    public function withoutHeight(): static
    {
        return $this->state(fn () => ['height_cm' => null]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn () => ['department_id' => $department->id]);
    }

    public function onTeam(Team $team): static
    {
        return $this->state(fn () => ['team_id' => $team->id]);
    }

    public function joinedOn(string $date): static
    {
        return $this->state(fn () => ['joined_at' => $date]);
    }

    public function leftOn(string $date): static
    {
        return $this->state(fn () => ['left_at' => $date]);
    }

    /** Runs the challenge but doesn't compete in it. */
    public function nonParticipant(): static
    {
        return $this->state(fn () => ['is_participant' => false]);
    }
}

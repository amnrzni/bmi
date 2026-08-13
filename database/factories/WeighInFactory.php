<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WeighIn;
use App\Support\ChallengeWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeighIn>
 */
class WeighInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_start_date' => ChallengeWeek::first()->key(),
            'weight_kg' => fake()->randomFloat(1, 50, 100),
            'recorded_by_user_id' => User::factory()->admin(),
        ];
    }

    public function forWeek(ChallengeWeek|int $week): static
    {
        $week = is_int($week) ? ChallengeWeek::fromNumber($week) : $week;

        return $this->state(fn () => ['week_start_date' => $week->key()]);
    }

    public function weighing(float $kg): static
    {
        return $this->state(fn () => ['weight_kg' => $kg]);
    }
}

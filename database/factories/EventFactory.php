<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+8 weeks');

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'starts_at' => $startsAt,
            'location' => fake()->city(),
            'rsvp_deadline' => (clone $startsAt)->modify('-2 days'),
            'created_by_user_id' => User::factory()->admin(),
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => fake()->dateTimeBetween('-6 weeks', '-1 day'),
            'rsvp_deadline' => null,
        ]);
    }
}

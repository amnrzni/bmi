<?php

namespace Database\Seeders;

use App\Enums\RsvpResponse;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventResponse;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo events. Includes past ones with attendance — without them turnout has no
 * denominator and every standings block reads "no events yet".
 */
class DemoEventSeeder extends Seeder
{
    /** [title, when (relative), location, description] */
    private const UPCOMING = [
        ['Senaman Pagi Beramai-ramai', '+2 days 07:00', 'Padang HQ', 'Teams mixed. Bring your own water.'],
        ['Hiking Bukit Gasing', '+16 days 06:30', 'Bukit Gasing', 'Teams mixed. Bring your own water.'],
        ['Futsal Antara Unit', '+30 days 20:00', 'Indoor court', 'Teams mixed.'],
    ];

    private const PAST = [
        ['Kickoff Walk', '-21 days 07:30', 'Padang HQ', 'Opening walk to start the challenge.'],
        ['Badminton Night', '-9 days 20:00', 'Indoor court', 'Casual doubles.'],
    ];

    public function run(): void
    {
        mt_srand(7);

        $admin = User::admins()->first() ?? User::first();
        $participants = User::participants()->get();

        foreach (self::UPCOMING as [$title, $when, $location, $description]) {
            $event = $this->makeEvent($title, $when, $location, $description, $admin);
            $this->seedResponses($event, $participants);
        }

        foreach (self::PAST as [$title, $when, $location, $description]) {
            $event = $this->makeEvent($title, $when, $location, $description, $admin, hasDeadline: false);
            $this->seedResponses($event, $participants);

            // Roughly 60% of those who said yes actually turned up, plus the
            // occasional person who came without RSVPing.
            foreach ($participants as $person) {
                $saidYes = $event->responses
                    ->firstWhere('user_id', $person->id)?->response === RsvpResponse::Yes;

                $chance = $saidYes ? 60 : 12;

                if (mt_rand(1, 100) > $chance) {
                    continue;
                }

                EventAttendance::firstOrCreate(
                    ['event_id' => $event->id, 'user_id' => $person->id],
                    [
                        'checked_in_at' => $event->starts_at->copy()->addMinutes(mt_rand(0, 45)),
                        'checked_in_by_user_id' => $person->id, // staff check themselves in
                    ],
                );
            }
        }
    }

    private function makeEvent(
        string $title,
        string $when,
        string $location,
        string $description,
        User $admin,
        bool $hasDeadline = true,
    ): Event {
        $startsAt = now()->modify($when);

        return Event::updateOrCreate(
            ['title' => $title],
            [
                'description' => $description,
                'starts_at' => $startsAt,
                'location' => $location,
                'rsvp_deadline' => $hasDeadline ? $startsAt->copy()->subDays(2) : null,
                'created_by_user_id' => $admin->id,
            ],
        );
    }

    private function seedResponses(Event $event, $participants): void
    {
        foreach ($participants as $person) {
            if (mt_rand(1, 100) > 65) {
                continue;
            }

            EventResponse::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $person->id],
                [
                    'response' => mt_rand(1, 100) <= 75 ? RsvpResponse::Yes : RsvpResponse::No,
                    'responded_at' => now(),
                ],
            );
        }

        $event->load('responses');
    }
}

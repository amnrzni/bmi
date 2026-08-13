<?php

/**
 * One rotation, this cycle only. Hardcoded on purpose — HANDOFF.md §1 rules out
 * multi-season abstraction. If it's ever reused, that's a rewrite.
 *
 * Timezone comes from APP_TIMEZONE (Asia/Kuala_Lumpur), so `now()` is already
 * local everywhere and week arithmetic needs no special handling.
 */
return [

    // Week 1 Monday. Every week in the app is derived from this date.
    'start_monday' => env('CHALLENGE_START_MONDAY', '2026-08-10'),

    // Open-ended: duration isn't fixed yet, so the weekly grid simply grows as
    // weeks are recorded. Set a date here to cap it.
    'end_date' => env('CHALLENGE_END_DATE'),

    // Weigh-in stays editable Monday 00:00 → Wednesday 23:59. Admins override this
    // and may edit any elapsed week, which is how week 1 gets backfilled at all.
    'edit_window_days' => 2,

    // Minimum weigh-ins before a person is ranked on the individual leaderboard.
    'min_records_rank' => 4,

    // Minimum before a person counts toward their team's average. Lower than the
    // leaderboard threshold deliberately: 2 records is the minimum needed to have
    // any delta, and requiring 4 would blank the team standings until week 5.
    // Raise to 4 to make the two rules identical.
    'min_records_team' => 2,

    // Typo guards on weight entry.
    'weight_min' => 30,
    'weight_max' => 250,
    'weight_jump_warn' => 5.0, // kg change vs last record that triggers a warning

    // Staff check themselves in on the day. The window opens this many minutes
    // before an event starts and closes at the end of that calendar day.
    'checkin_opens_before_minutes' => 60,

    // Malaysian / Asian BMI cutoffs — NOT the WHO 25/30 thresholds.
    'bmi_cutoffs' => [
        'underweight' => 18.5, // below this
        'normal' => 23.0, // below this
        'overweight' => 27.5, // below this; at or above is obese
    ],

];

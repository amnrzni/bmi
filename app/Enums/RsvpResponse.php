<?php

namespace App\Enums;

/** Yes/no only — "maybe" was dropped as noise for a headcount. */
enum RsvpResponse: string
{
    case Yes = 'yes';
    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Going',
            self::No => 'Not going',
        };
    }
}

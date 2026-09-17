<?php

namespace App\Enums;

/**
 * A tournament's two brackets. Held on the squad row, never on the user —
 * the challenge stores no gender (DECISIONS.md §3), and which side someone
 * plays on is a fact about this tournament, not about them.
 */
enum Division: string
{
    case Men = 'men';
    case Women = 'women';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Men',
            self::Women => 'Women',
        };
    }
}

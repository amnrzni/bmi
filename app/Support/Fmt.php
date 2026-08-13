<?php

namespace App\Support;

/**
 * Shared number formatting for deltas.
 *
 * Colour convention across the whole app, matching the mock: gold = down
 * (weight lost), red = up (gained), dim = flat or no data.
 */
final class Fmt
{
    /** Below this magnitude a change is treated as flat rather than a direction. */
    private const EPSILON = 0.05;

    /** "-2.4" / "+1.1" / "0.0" */
    public static function delta(?float $value, int $decimals = 1): string
    {
        if ($value === null) {
            return '—';
        }

        return ($value > 0 ? '+' : '').number_format($value, $decimals);
    }

    /** "-3.2%" */
    public static function percent(?float $value, int $decimals = 1): string
    {
        if ($value === null) {
            return '—';
        }

        return ($value > 0 ? '+' : '').number_format($value, $decimals).'%';
    }

    public static function weight(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1);
    }

    public static function bmi(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1);
    }

    /** Tailwind text colour for a delta. Down is good. */
    public static function deltaColor(?float $value): string
    {
        return match (true) {
            $value === null => 'text-bone-dim',
            $value < -self::EPSILON => 'text-gold',
            $value > self::EPSILON => 'text-blood-bright',
            default => 'text-bone-dim',
        };
    }
}

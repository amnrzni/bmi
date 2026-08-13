<?php

namespace App\Enums;

/**
 * Malaysian / Asian cutoffs, not WHO. Thresholds live in config/challenge.php.
 */
enum BmiCategory: string
{
    case Underweight = 'underweight';
    case Normal = 'normal';
    case Overweight = 'overweight';
    case Obese = 'obese';

    public static function forBmi(?float $bmi): ?self
    {
        if ($bmi === null || $bmi <= 0) {
            return null;
        }

        $cutoffs = config('challenge.bmi_cutoffs');

        return match (true) {
            $bmi < $cutoffs['underweight'] => self::Underweight,
            $bmi < $cutoffs['normal'] => self::Normal,
            $bmi < $cutoffs['overweight'] => self::Overweight,
            default => self::Obese,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Underweight => 'Underweight',
            self::Normal => 'Normal',
            self::Overweight => 'Overweight',
            self::Obese => 'Obese',
        };
    }

    /** Tailwind text colour token for the verdict readout. */
    public function colorClass(): string
    {
        return match ($this) {
            self::Underweight => 'text-gold-bright',
            self::Normal => 'text-gold',
            self::Overweight => 'text-blood-bright',
            self::Obese => 'text-blood',
        };
    }
}

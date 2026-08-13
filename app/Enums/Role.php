<?php

namespace App\Enums;

/**
 * PIC is folded into Admin for this build — an accepted separation-of-duties gap
 * for a low-stakes throwaway (HANDOFF.md §8). Do not carry this into QCXIS proper.
 */
enum Role: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }
}

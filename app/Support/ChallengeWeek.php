<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * All week arithmetic lives here, in PHP.
 *
 * Nothing in this app may compute weeks in SQL: local dev is SQLite and
 * production is MySQL, and their date functions differ. Keeping it in Carbon
 * means the two behave identically.
 *
 * A week is identified by its Monday (`week_start_date`), never a bare week
 * number — HANDOFF.md §2.
 */
final class ChallengeWeek
{
    public function __construct(
        public readonly CarbonImmutable $startDate,
    ) {}

    /** Snap any date to the Monday of its week. */
    public static function fromDate(DateTimeInterface|string $date): self
    {
        return new self(
            CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay()
        );
    }

    /** Week 1, from config. */
    public static function first(): self
    {
        return self::fromDate(config('challenge.start_monday'));
    }

    /** The week we're in right now. */
    public static function current(): self
    {
        return self::fromDate(CarbonImmutable::now());
    }

    /** Week 1 through the current week, oldest first. @return Collection<int,self> */
    public static function elapsed(): Collection
    {
        $weeks = collect();
        $week = self::first();
        $current = self::current();

        // Guard against a start date in the future.
        if ($week->startDate->greaterThan($current->startDate)) {
            return $weeks;
        }

        while ($week->startDate->lessThanOrEqualTo($current->startDate)) {
            $weeks->push($week);
            $week = $week->next();
        }

        return $weeks;
    }

    /** Look up a week by its 1-based number. Returns null for numbers below 1. */
    public static function fromNumber(int $number): ?self
    {
        if ($number < 1) {
            return null;
        }

        return new self(self::first()->startDate->addWeeks($number - 1));
    }

    /** 1-based position in the challenge. Weeks before the start return 0 or lower. */
    public function number(): int
    {
        return (int) self::first()->startDate->diffInWeeks($this->startDate) + 1;
    }

    /** The Sunday. */
    public function endDate(): CarbonImmutable
    {
        return $this->startDate->addDays(6)->endOfDay();
    }

    /**
     * When normal (non-admin) editing stops: end of Wednesday by default.
     * Admins ignore this entirely.
     */
    public function closesAt(): CarbonImmutable
    {
        return $this->startDate
            ->addDays((int) config('challenge.edit_window_days'))
            ->endOfDay();
    }

    /** Still inside the normal editing window? */
    public function isOpen(): bool
    {
        $now = CarbonImmutable::now();

        return $now->greaterThanOrEqualTo($this->startDate)
            && $now->lessThanOrEqualTo($this->closesAt());
    }

    /** Has this week begun? Admins may only backfill weeks that have. */
    public function hasElapsed(): bool
    {
        return $this->startDate->lessThanOrEqualTo(CarbonImmutable::now());
    }

    public function isCurrent(): bool
    {
        return $this->startDate->isSameDay(self::current()->startDate);
    }

    public function next(): self
    {
        return new self($this->startDate->addWeek());
    }

    public function previous(): self
    {
        return new self($this->startDate->subWeek());
    }

    /** "M10" — the weekly grid's column header. */
    public function shortLabel(): string
    {
        return 'W'.$this->number();
    }

    /** "Week 10 — 10 to 16 Aug 2026" */
    public function label(): string
    {
        return sprintf(
            'Week %d — %s to %s',
            $this->number(),
            $this->startDate->format('j M'),
            $this->endDate()->format('j M Y'),
        );
    }

    /** The value stored in weigh_ins.week_start_date. */
    public function key(): string
    {
        return $this->startDate->toDateString();
    }

    public function equals(self $other): bool
    {
        return $this->key() === $other->key();
    }
}

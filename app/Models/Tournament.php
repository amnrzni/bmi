<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A one-day sports tournament: squads picked from the roster, a round-robin
 * group per division, and a final between each division's top two.
 *
 * Separate from the challenge's own teams — a tournament draws its squads
 * fresh, so nothing here reads or writes `users.team_id`.
 */
class Tournament extends Model
{
    protected $guarded = ['id'];

    /** Row styles for the running order. Display only; nothing keys on them. */
    public const PROGRAMME_KINDS = ['general', 'match', 'break', 'final'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'programme' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class)->orderBy('sort_order')->orderBy('id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentFixture::class)->orderBy('number');
    }

    public function finals(): HasMany
    {
        return $this->hasMany(TournamentFinal::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The running order with every key present, so a hand-edited or partial
     * row can't break the page.
     *
     * @return list<array{time: string, activity: string, detail: string, kind: string}>
     */
    public function programmeRows(): array
    {
        return collect($this->programme ?? [])
            ->map(fn ($row) => [
                'time' => (string) ($row['time'] ?? ''),
                'activity' => (string) ($row['activity'] ?? ''),
                'detail' => (string) ($row['detail'] ?? ''),
                'kind' => in_array($row['kind'] ?? null, self::PROGRAMME_KINDS, true) ? $row['kind'] : 'general',
            ])
            ->values()
            ->all();
    }

    /** A URL slug for a new tournament, suffixed until it is unique. */
    public static function slugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'tournament';

        $slug = $base;
        $suffix = 2;

        while (self::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

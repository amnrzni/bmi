<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\ChallengeWeek;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Auth identity, org identity and challenge overlay in one table.
 *
 * Department is permanent org identity; team is the competitive overlay and is
 * assigned per-individual, so a department can be split across both teams.
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_participant' => 'boolean',
            'height_cm' => 'integer',
            'joined_at' => 'date',
            'left_at' => 'date',
            'consented_at' => 'datetime',
            'anonymised_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function weighIns(): HasMany
    {
        return $this->hasMany(WeighIn::class)->orderBy('week_start_date');
    }

    public function eventResponses(): HasMany
    {
        return $this->hasMany(EventResponse::class);
    }

    public function eventAttendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    // ------------------------------------------------------------------- scopes

    public function scopeParticipants(Builder $query): Builder
    {
        return $query->where('is_participant', true);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', Role::Admin);
    }

    /**
     * Who was on the roster for a given week — the denominator for "who didn't
     * submit". Someone who left keeps their logged weeks but stops appearing here,
     * so a roster edit never rewrites history (HANDOFF.md §5.3).
     */
    public function scopeOnRosterFor(Builder $query, ChallengeWeek $week): Builder
    {
        return $query
            ->where('is_participant', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('joined_at')
                ->orWhereDate('joined_at', '<=', $week->endDate()->toDateString()))
            ->where(fn (Builder $q) => $q
                ->whereNull('left_at')
                ->orWhereDate('left_at', '>=', $week->startDate->toDateString()));
    }

    // ---------------------------------------------------------------- behaviour

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function hasConsented(): bool
    {
        return $this->consented_at !== null;
    }

    /**
     * Has never got in. Almost always means their email doesn't match the one
     * QCXIS returns for them — the roster match is on email alone.
     */
    public function hasNeverSignedIn(): bool
    {
        return $this->last_login_at === null;
    }

    /** Left the challenge; keeps their history, stops generating missing flags. */
    public function hasLeft(): bool
    {
        return $this->left_at !== null;
    }

    /** Height in metres, or null when it was never captured. */
    public function heightM(): ?float
    {
        return $this->height_cm ? $this->height_cm / 100 : null;
    }

    /**
     * BMI for an arbitrary weight against this person's height.
     * Always computed here — never trusted from a client (HANDOFF.md §2).
     */
    public function bmiFor(?float $weightKg): ?float
    {
        $heightM = $this->heightM();

        if (! $weightKg || $weightKg <= 0 || ! $heightM) {
            return null;
        }

        return round($weightKg / ($heightM ** 2), 2);
    }

    /** Withdrawn users keep their rows for the team aggregate but lose their name. */
    public function displayName(): string
    {
        return $this->anonymised_at ? 'Withdrawn member' : $this->name;
    }
}

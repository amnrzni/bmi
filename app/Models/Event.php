<?php

namespace App\Models;

use App\Enums\RsvpResponse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Event extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'rsvp_deadline' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'starts_at', 'location', 'rsvp_deadline'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EventResponse::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now())->orderByDesc('starts_at');
    }

    public function rsvpIsOpen(): bool
    {
        return $this->rsvp_deadline === null || now()->lessThanOrEqualTo($this->rsvp_deadline);
    }

    /** The instant headcount that justifies this over a Google Form. */
    public function goingCount(): int
    {
        // Counts in memory when responses are already loaded, so a list of
        // events doesn't fire a query per row.
        if ($this->relationLoaded('responses')) {
            return $this->responses->where('response', RsvpResponse::Yes)->count();
        }

        return $this->responses()->where('response', RsvpResponse::Yes->value)->count();
    }

    // ---------------------------------------------------------------- check-in

    /**
     * Staff check themselves in, so the window has to be bounded at both ends:
     * open too early and people mark themselves present for something they
     * haven't attended; never close it and they check in weeks later.
     *
     * Opens shortly before the start, closes at the end of that calendar day.
     */
    public function checkInOpensAt(): CarbonInterface
    {
        return $this->starts_at->copy()
            ->subMinutes((int) config('challenge.checkin_opens_before_minutes'));
    }

    public function checkInClosesAt(): CarbonInterface
    {
        return $this->starts_at->copy()->endOfDay();
    }

    public function checkInIsOpen(): bool
    {
        return now()->betweenIncluded($this->checkInOpensAt(), $this->checkInClosesAt());
    }

    public function hasAttended(User $user): bool
    {
        if ($this->relationLoaded('attendances')) {
            return $this->attendances->contains('user_id', $user->id);
        }

        return $this->attendances()->where('user_id', $user->id)->exists();
    }

    public function attendedCount(): int
    {
        if ($this->relationLoaded('attendances')) {
            return $this->attendances->count();
        }

        return $this->attendances()->count();
    }
}

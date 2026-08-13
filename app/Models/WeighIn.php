<?php

namespace App\Models;

use App\Enums\BmiCategory;
use App\Support\ChallengeWeek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One row per staff per week. Never overwritten across weeks — the series is
 * the whole point. Writes are keyed on (user_id, week_start_date).
 */
class WeighIn extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            // Explicit casts matter: SQLite is loosely typed, MySQL is not.
            'weight_kg' => 'decimal:2',
            'bmi' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'week_start_date', 'weight_kg', 'bmi', 'recorded_by_user_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Provenance: staff don't self-log, so the UI shows who recorded this. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeForWeek(Builder $query, ChallengeWeek $week): Builder
    {
        return $query->whereDate('week_start_date', $week->key());
    }

    public function week(): ChallengeWeek
    {
        return ChallengeWeek::fromDate($this->week_start_date);
    }

    public function bmiCategory(): ?BmiCategory
    {
        return BmiCategory::forBmi($this->bmi ? (float) $this->bmi : null);
    }
}

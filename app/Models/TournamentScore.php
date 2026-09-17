<?php

namespace App\Models;

use App\Enums\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** One division's result for a group fixture. Written only through TournamentRecorder. */
class TournamentScore extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'division' => Division::class,
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tournament_fixture_id', 'division', 'home_score', 'away_score', 'recorded_by_user_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(TournamentFixture::class, 'tournament_fixture_id');
    }
}

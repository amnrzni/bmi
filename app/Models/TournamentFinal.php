<?php

namespace App\Models;

use App\Enums\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** A division's final. Written only through TournamentRecorder. */
class TournamentFinal extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'division' => Division::class,
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_penalties' => 'integer',
            'away_penalties' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'division', 'home_team_id', 'away_team_id', 'home_score', 'away_score',
                'home_penalties', 'away_penalties', 'recorded_by_user_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function wentToPenalties(): bool
    {
        return $this->home_score === $this->away_score;
    }

    /** The recorder refuses a level final without a decisive shoot-out, so there is always one. */
    public function winnerTeamId(): int
    {
        $homeWon = $this->wentToPenalties()
            ? $this->home_penalties > $this->away_penalties
            : $this->home_score > $this->away_score;

        return $homeWon ? $this->home_team_id : $this->away_team_id;
    }
}

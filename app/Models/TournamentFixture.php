<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group-stage pairing, played once in each division. Its scores hang off the
 * fixture, so changing the pairing keeps them — they follow the match number.
 */
class TournamentFixture extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'away_team_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(TournamentScore::class);
    }
}

<?php

namespace App\Models;

use App\Enums\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A roster member placed in a tournament squad. */
class TournamentPlayer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'division' => Division::class,
            'is_captain' => 'boolean',
            'is_out' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'tournament_team_id');
    }

    /** Trashed included: someone deleted from the roster still played in this squad. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function displayName(): string
    {
        return $this->user?->displayName() ?? 'Removed member';
    }
}

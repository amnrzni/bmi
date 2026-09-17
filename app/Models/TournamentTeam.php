<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One side in a tournament. Named per tournament; unrelated to challenge teams. */
class TournamentTeam extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** In the order they were added, so toggling a captain never reshuffles the list. */
    public function players(): HasMany
    {
        return $this->hasMany(TournamentPlayer::class)->orderBy('id');
    }
}

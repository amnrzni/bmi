<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The competitive unit. TAH and BKN this cycle.
 * Both get equal visual weight everywhere — no favoured team.
 */
class Team extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

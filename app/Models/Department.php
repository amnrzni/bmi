<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Permanent org identity and the collection bucket for weigh-in progress.
 * Explicitly NOT a competitive unit — HANDOFF.md §2.
 */
class Department extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** No DB-level FK — departments and users reference each other. */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}

<?php

namespace App\Models;

use App\Support\MerdekaRubric;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A filed scoresheet. Immutable once written — the panel signs it, so changing
 * it afterwards would make the signature attest to something else.
 */
class MerdekaScore extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scales' => 'array',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** The band this sheet gave a criterion, or null if the key is absent. */
    public function band(string $criterion): ?int
    {
        $band = $this->scales[$criterion] ?? null;

        return $band === null ? null : (int) $band;
    }

    /** Marks that band earned, recomputed from the rubric rather than stored per-criterion. */
    public function earned(string $criterion): float
    {
        $band = $this->band($criterion);

        return $band === null ? 0.0 : MerdekaRubric::earned($criterion, $band);
    }
}

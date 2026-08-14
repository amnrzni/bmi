<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The competitive unit. Admin-managed: a set of named teams the office defines,
 * ranked against each other on the home page by average % change from baseline.
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

    /**
     * A short tag derived from a team name, for the compact weekly-grid column.
     *
     * The admin only ever types a name; this keeps `code` out of their way while
     * staying unique — the column is a unique index. `$ignoreId` lets a rename
     * keep its own code.
     */
    public static function codeFor(string $name, ?int $ignoreId = null): string
    {
        // First three alphanumerics of the name, uppercased. Falls back to "TM"
        // for a name with nothing usable (e.g. only punctuation).
        $base = Str::of($name)->ascii()->replaceMatches('/[^A-Za-z0-9]/', '')->upper()->substr(0, 3);
        $base = $base->isEmpty() ? 'TM' : (string) $base;

        $code = $base;
        $suffix = 2;

        while (self::where('code', $code)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }
}

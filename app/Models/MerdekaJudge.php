<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seat on the Merdeka judging panel — and, since the contest doesn't use app
 * auth, the judge's whole identity.
 *
 * The email is the credential. Anyone who types a listed address is that judge,
 * which is the trade the contest accepts for not making two founders go through
 * QCXIS for a decoration contest.
 */
class MerdekaJudge extends Model
{
    use HasFactory;

    /** Where the signed-in judge's id lives for the browser session. */
    public const SESSION_KEY = 'merdeka_judge_id';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(MerdekaScore::class);
    }

    /** The one lookup the sign-in screen makes. Case-insensitive by construction. */
    public static function findByEmail(string $email): ?self
    {
        return self::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /**
     * The judge this browser session is signed in as.
     *
     * Re-read every time rather than cached in the session, so removing someone
     * from the panel shuts their open tab out on the next request.
     */
    public static function current(): ?self
    {
        $id = session(self::SESSION_KEY);

        return $id ? self::find($id) : null;
    }

    public function label(): string
    {
        return $this->title ?: 'Panel Hakim';
    }
}

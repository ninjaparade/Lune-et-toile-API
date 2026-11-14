<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['created_by', 'white_id', 'black_id', 'invited_id', 'status', 'moves', 'result', 'termination'])]
class Game extends Model
{
    use HasUlids;

    public const WAITING = 'waiting';

    public const ACTIVE = 'active';

    public const FINISHED = 'finished';

    protected $attributes = [
        'status' => self::WAITING,
        'moves' => '[]',
    ];

    protected function casts(): array
    {
        return ['moves' => 'array'];
    }

    /**
     * The ULID goes in `uid`; the integer `id` stays the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function white(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_id');
    }

    public function black(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_id');
    }

    public function invited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_id');
    }

    /**
     * Games the user plays in or has been invited to.
     */
    public function scopeInvolving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('white_id', $user->id)
            ->orWhere('black_id', $user->id)
            ->orWhere('invited_id', $user->id));
    }

    public function isPlayer(User $user): bool
    {
        return in_array($user->id, [$this->white_id, $this->black_id], true);
    }

    public function canBeSeenBy(User $user): bool
    {
        return $this->isPlayer($user) || $this->invited_id === $user->id;
    }

    /**
     * Whose turn it is by ply count: even = white, odd = black.
     */
    public function playerToMoveId(): ?int
    {
        return count($this->moves) % 2 === 0 ? $this->white_id : $this->black_id;
    }
}

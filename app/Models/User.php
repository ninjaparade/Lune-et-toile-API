<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * The shareable ULID goes in `uid`; the integer `id` stays the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')->withTimestamps();
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Games waiting on this player — their move or an invitation. Used as the app badge.
     */
    public function pendingCount(): int
    {
        $invites = Game::where('status', Game::WAITING)->where('invited_id', $this->id)->count();
        $turns = Game::involving($this)->where('status', Game::ACTIVE)->get(['white_id', 'black_id', 'moves'])
            ->filter(fn (Game $game) => $game->playerToMoveId() === $this->id)
            ->count();

        return $invites + $turns;
    }

    public function befriend(User $other): void
    {
        $this->friends()->syncWithoutDetaching([$other->id]);
        $other->friends()->syncWithoutDetaching([$this->id]);
    }

    public function unfriend(User $other): void
    {
        $this->friends()->detach($other->id);
        $other->friends()->detach($this->id);
    }

    public function isFriendsWith(User $other): bool
    {
        return $this->friends()->whereKey($other->id)->exists();
    }

    /**
     * Turn email invitations sent to this user's address into friendships.
     */
    public function acceptInvitations(): void
    {
        Invitation::where('email', $this->email)->with('inviter')->get()
            ->each(function (Invitation $invitation) {
                $this->befriend($invitation->inviter);
                $invitation->delete();
            });
    }

    /**
     * Finished games this user played, optionally only those against `$opponent`.
     */
    public function finishedGames(?User $opponent = null): Builder
    {
        return Game::query()
            ->where('status', Game::FINISHED)
            ->where(fn (Builder $q) => $q->where('white_id', $this->id)->orWhere('black_id', $this->id))
            ->when($opponent, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('white_id', $opponent->id)->orWhere('black_id', $opponent->id)));
    }

    /**
     * Win/loss/draw counts, computed from finished games.
     *
     * @return array{wins: int, losses: int, draws: int}
     */
    public function record(?User $opponent = null): array
    {
        $record = ['wins' => 0, 'losses' => 0, 'draws' => 0];

        foreach ($this->finishedGames($opponent)->get(['white_id', 'black_id', 'result']) as $game) {
            $won = ($game->white_id === $this->id && $game->result === '1-0')
                || ($game->black_id === $this->id && $game->result === '0-1');
            $key = match (true) {
                $game->result === '1/2-1/2' => 'draws',
                $won => 'wins',
                default => 'losses',
            };
            $record[$key]++;
        }

        return $record;
    }
}

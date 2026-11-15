<?php

namespace App\Support;

use App\Models\Game;
use App\Models\User;
use App\Services\Apns;

use function Illuminate\Support\defer;

/**
 * The notifications a game sends. Deliberately generic — no names or move details on the
 * lock screen. Sent after the response so a slow APNs round trip never holds up a move.
 */
class GamePush
{
    public static function invited(Game $game): void
    {
        self::send($game->invited, "You've been invited to a new game.", $game);
    }

    public static function accepted(Game $game, User $by): void
    {
        $creator = $game->white_id === $by->id ? $game->black : $game->white;
        $body = $game->white_id === $creator?->id ? "Your game was accepted — it's your move." : 'Your game was accepted.';
        self::send($creator, $body, $game);
    }

    public static function declined(Game $game): void
    {
        self::send(User::find($game->created_by), 'Your game invitation was declined.', $game);
    }

    public static function moved(Game $game, User $by): void
    {
        $body = match (true) {
            $game->status !== Game::FINISHED => "It's your move.",
            $game->result === '1/2-1/2' => 'The game ended in a draw.',
            default => 'Checkmate — you lost this one.',
        };
        self::send(self::opponentOf($game, $by), $body, $game);
    }

    public static function resigned(Game $game, User $by): void
    {
        self::send(self::opponentOf($game, $by), 'Your opponent resigned — you won!', $game);
    }

    private static function opponentOf(Game $game, User $player): ?User
    {
        return $game->white_id === $player->id ? $game->black : $game->white;
    }

    private static function send(?User $to, string $body, Game $game): void
    {
        if (! $to) {
            return;
        }

        defer(fn () => app(Apns::class)->send($to, $body, ['game' => $game->uid]));
    }
}

<?php

namespace App\Support;

use App\Models\Game;
use App\Models\User;
use App\Services\Apns;

use function Illuminate\Support\defer;

/**
 * The notifications a game sends, worded for the person receiving them. Sent after the
 * response so a slow APNs round trip never holds up a move.
 */
class GamePush
{
    public static function invited(Game $game, User $by): void
    {
        $color = $game->white_id ? 'Black' : 'White';
        self::send($game->invited, $by->name, "Invited you to a game — you'd play {$color}.", $game);
    }

    public static function accepted(Game $game, User $by): void
    {
        $creator = $game->white_id === $by->id ? $game->black : $game->white;
        $next = $game->white_id === $creator->id ? 'Your move.' : 'They move first.';
        self::send($creator, $by->name, "Accepted your game. {$next}", $game);
    }

    public static function declined(Game $game, User $by): void
    {
        $creator = User::find($game->created_by);
        self::send($creator, $by->name, 'Declined your game.', $game);
    }

    public static function moved(Game $game, User $by, ?string $san, ?string $captured): void
    {
        $opponent = $game->white_id === $by->id ? $game->black : $game->white;
        $played = self::describe($by->firstName(), $san, $captured).'.';

        if ($game->status !== Game::FINISHED) {
            $next = str_contains((string) $san, '+') ? 'Check — your move.' : 'Your move.';
            self::send($opponent, "vs {$by->name}", "{$played} {$next}", $game);

            return;
        }

        $outcome = match (true) {
            $game->result === '1/2-1/2' => "It's a draw by {$game->termination}.",
            $game->termination === 'checkmate' => 'Checkmate — you lost this one.',
            default => "{$by->firstName()} won by {$game->termination}.",
        };
        self::send($opponent, "vs {$by->name}", "{$played} {$outcome}", $game);
    }

    /**
     * Plain-English move from the SAN the app sends plus the piece it captured:
     * "Yaz moved a knight", "Yaz's pawn took your rook", "Yaz castled kingside".
     */
    public static function describe(string $who, ?string $san, ?string $captured): string
    {
        if (! $san) {
            return "{$who} moved";
        }
        if (str_starts_with($san, 'O-O')) {
            return $who.(str_starts_with($san, 'O-O-O') ? ' castled queenside' : ' castled kingside');
        }

        $names = ['N' => 'knight', 'B' => 'bishop', 'R' => 'rook', 'Q' => 'queen', 'K' => 'king'];
        $piece = $names[$san[0]] ?? 'pawn';
        $text = str_contains($san, 'x') && $captured
            ? "{$who}'s {$piece} took your {$captured}"
            : "{$who} moved a {$piece}";

        if (preg_match('/=([QRBN])/', $san, $promotion)) {
            $text .= ' and promoted it to a '.$names[$promotion[1]];
        }

        return $text;
    }

    public static function resigned(Game $game, User $by): void
    {
        $opponent = $game->white_id === $by->id ? $game->black : $game->white;
        self::send($opponent, "vs {$by->name}", "{$by->firstName()} resigned — you win!", $game);
    }

    private static function send(?User $to, string $title, string $body, Game $game): void
    {
        if (! $to) {
            return;
        }

        defer(fn () => app(Apns::class)->send($to, $title, $body, ['game' => $game->uid]));
    }
}

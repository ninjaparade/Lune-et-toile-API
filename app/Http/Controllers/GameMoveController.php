<?php

namespace App\Http\Controllers;

use App\Events\GameUpdated;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Support\GamePush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Appends a move. Legality is checked on the devices (each replays the full move
 * list); the server enforces whose turn it is and that the client is in sync.
 */
class GameMoveController extends Controller
{
    public function store(Request $request, Game $game): GameResource
    {
        $data = $request->validate([
            'uci' => ['required', 'string', 'regex:/\A[a-h][1-8][a-h][1-8][qrbn]?\z/'],
            'ply' => ['required', 'integer', 'min:0'],
            'result' => ['nullable', Rule::in(['1-0', '0-1', '1/2-1/2'])],
            'termination' => ['nullable', 'required_with:result', 'string', 'max:32'],
            // Only used to word the opponent's notification ("Yaz's pawn took your rook").
            'san' => ['nullable', 'string', 'max:10'],
            'captured' => ['nullable', Rule::in(['pawn', 'knight', 'bishop', 'rook', 'queen'])],
        ]);

        $user = $request->user();
        abort_unless($game->isPlayer($user), 404);

        $game = DB::transaction(function () use ($game, $data, $user) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            abort_unless($game->status === Game::ACTIVE, 409, 'This game is not in progress.');
            abort_unless($data['ply'] === count($game->moves), 409, 'Out of sync — reload the game.');
            abort_unless($game->playerToMoveId() === $user->id, 403, "It's not your turn.");

            $game->moves = [...$game->moves, $data['uci']];
            if (isset($data['result'])) {
                $game->status = Game::FINISHED;
                $game->result = $data['result'];
                $game->termination = $data['termination'];
            }
            $game->save();

            return $game;
        });

        GameUpdated::send($game->load(['white', 'black', 'invited']));
        GamePush::moved($game, $user, $data['san'] ?? null, $data['captured'] ?? null);

        return new GameResource($game);
    }
}

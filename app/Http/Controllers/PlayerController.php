<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Http\Resources\PlayerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A player's record and finished games — yourself, or a friend (then also your
 * head-to-head record and only the games the two of you played).
 */
class PlayerController extends Controller
{
    public function show(Request $request, User $player): JsonResponse
    {
        $me = $request->user();
        $isMe = $me->is($player);
        abort_unless($isMe || $me->isFriendsWith($player), 404);

        $games = $player->finishedGames($isMe ? null : $me)
            ->with(['white', 'black', 'invited'])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => [
            'player' => new PlayerResource($player),
            'record' => $player->record(),
            'head_to_head' => $isMe ? null : $me->record($player),
            'games' => GameResource::collection($games),
        ]]);
    }
}

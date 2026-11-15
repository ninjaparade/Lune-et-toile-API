<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Resources\MessageResource;
use App\Models\Game;
use App\Support\GamePush;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Chat between the two players of a game (oldest first).
 */
class GameMessageController extends Controller
{
    private const HISTORY = 200;

    public function index(Request $request, Game $game): AnonymousResourceCollection
    {
        abort_unless($game->isPlayer($request->user()), 404);

        $messages = $game->messages()->latest('id')->limit(self::HISTORY)->get()->reverse()->values();

        return MessageResource::collection($messages);
    }

    public function store(Request $request, Game $game): MessageResource
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        $user = $request->user();
        abort_unless($game->isPlayer($user), 404);
        abort_unless($game->white_id && $game->black_id, 409, 'There is no one to chat with yet.');

        $message = $game->messages()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);

        MessageSent::send($game, $message);
        GamePush::messaged($game, $user);

        return new MessageResource($message);
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\GameUpdated;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\User;
use App\Support\GamePush;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    private const RELATIONS = ['white', 'black', 'invited'];

    private const NUDGE_COOLDOWN = 900;

    public function index(Request $request): AnonymousResourceCollection
    {
        $games = Game::involving($request->user())->with(self::RELATIONS)->latest('updated_at')->get();

        return GameResource::collection($games);
    }

    /**
     * Create a game as white, black or random. Optionally invite a friend in the same call.
     */
    public function store(Request $request): GameResource
    {
        $data = $request->validate([
            'color' => ['required', Rule::in(['white', 'black', 'random'])],
            'friend_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $color = $data['color'] === 'random' ? (random_int(0, 1) ? 'white' : 'black') : $data['color'];

        $game = Game::create([
            'created_by' => $user->id,
            "{$color}_id" => $user->id,
            'invited_id' => isset($data['friend_id']) ? $this->friendOf($user, $data['friend_id'])->id : null,
        ]);

        GameUpdated::send($game->refresh()->load(self::RELATIONS));
        if ($game->invited) {
            GamePush::invited($game);
        }

        return new GameResource($game);
    }

    public function show(Request $request, Game $game): GameResource
    {
        abort_unless($game->canBeSeenBy($request->user()), 404);

        return new GameResource($game->load(self::RELATIONS));
    }

    public function invite(Request $request, Game $game): GameResource
    {
        $user = $request->user();
        abort_unless($game->isPlayer($user), 404);
        abort_unless($game->status === Game::WAITING, 409, 'This game already has two players.');

        $data = $request->validate(['friend_id' => ['required', 'integer']]);
        $game->update(['invited_id' => $this->friendOf($user, $data['friend_id'])->id]);
        GameUpdated::send($game->load(self::RELATIONS));
        GamePush::invited($game);

        return new GameResource($game->load(self::RELATIONS));
    }

    public function accept(Request $request, Game $game): GameResource
    {
        $user = $request->user();
        abort_unless($game->invited_id === $user->id, 404);
        abort_unless($game->status === Game::WAITING, 409, 'This game already has two players.');

        $game->update([
            $game->white_id ? 'black_id' : 'white_id' => $user->id,
            'invited_id' => null,
            'status' => Game::ACTIVE,
        ]);
        GameUpdated::send($game->load(self::RELATIONS));
        GamePush::accepted($game, $user);

        return new GameResource($game->load(self::RELATIONS));
    }

    public function decline(Request $request, Game $game): GameResource
    {
        abort_unless($game->invited_id === $request->user()->id, 404);

        $game->update(['invited_id' => null]);
        GameUpdated::send($game->load(self::RELATIONS));
        GamePush::declined($game);

        return new GameResource($game->load(self::RELATIONS));
    }

    public function resign(Request $request, Game $game): GameResource
    {
        $user = $request->user();
        abort_unless($game->isPlayer($user), 404);
        abort_unless($game->status === Game::ACTIVE, 409, 'This game is not in progress.');

        $game->update([
            'status' => Game::FINISHED,
            'result' => $game->white_id === $user->id ? '0-1' : '1-0',
            'termination' => 'resignation',
        ]);
        GameUpdated::send($game->load(self::RELATIONS));
        GamePush::resigned($game, $user);

        return new GameResource($game->load(self::RELATIONS));
    }

    /**
     * Remind the opponent it's their move. Once every 15 minutes per game, per player.
     */
    public function nudge(Request $request, Game $game): Response
    {
        $user = $request->user();
        abort_unless($game->isPlayer($user), 404);
        abort_unless($game->status === Game::ACTIVE, 409, 'This game is not in progress.');
        abort_if($game->playerToMoveId() === $user->id, 409, "It's your move.");

        $key = "nudge:{$game->id}:{$user->id}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            abort(429, "You can nudge again in {$minutes} min.");
        }
        RateLimiter::hit($key, self::NUDGE_COOLDOWN);

        GamePush::nudged($game, $user);

        return response()->noContent();
    }

    private function friendOf(User $user, int $friendId): User
    {
        $friend = $user->friends()->whereKey($friendId)->first();

        if (! $friend) {
            throw ValidationException::withMessages(['friend_id' => 'You can only invite your friends.']);
        }

        return $friend;
    }
}

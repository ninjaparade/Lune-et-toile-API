<?php

namespace App\Events;

use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sent on every change to a game, to the game's presence channel and to each
 * involved user's feed. Carries the full game so clients don't need to refetch.
 */
class GameUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Game $game) {}

    /**
     * Broadcast without letting a websocket outage fail the API request.
     */
    public static function send(Game $game): void
    {
        try {
            event(new self($game));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $userIds = array_unique(array_filter([$this->game->white_id, $this->game->black_id, $this->game->invited_id]));

        return [
            new PresenceChannel("game.{$this->game->uid}"),
            ...array_map(fn (int $id) => new PrivateChannel("user.{$id}"), $userIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['game' => (new GameResource($this->game->loadMissing(['white', 'black', 'invited'])))->resolve()];
    }
}

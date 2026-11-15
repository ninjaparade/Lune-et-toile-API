<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Game;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * A chat message, delivered live to whoever has the game open.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Game $game, public Message $message) {}

    /**
     * Broadcast without letting a websocket outage fail the API request.
     */
    public static function send(Game $game, Message $message): void
    {
        try {
            event(new self($game, $message));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("game.{$this->game->uid}")];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => (new MessageResource($this->message))->resolve()];
    }
}

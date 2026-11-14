<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Per-user feed: game created/invited/accepted/moved — keeps the games list live.
 */
Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->id === $id);

/**
 * Presence channel per game: players see each other online and receive moves instantly.
 */
Broadcast::channel('game.{uid}', function (User $user, string $uid) {
    $game = Game::where('uid', $uid)->first();

    if (! $game?->isPlayer($user)) {
        return false;
    }

    return ['id' => $user->id, 'username' => $user->username, 'name' => $user->name];
});

<?php

use App\Models\Game;
use App\Models\User;

function finishedGame(User $white, User $black, string $result): Game
{
    return Game::create([
        'created_by' => $white->id, 'white_id' => $white->id, 'black_id' => $black->id,
        'status' => 'finished', 'result' => $result, 'termination' => 'checkmate', 'moves' => ['f2f3'],
    ]);
}

it('counts wins, losses and draws from finished games', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();
    finishedGame($yaz, $andy, '1-0');
    finishedGame($andy, $yaz, '1-0');
    finishedGame($andy, $yaz, '0-1');
    finishedGame($yaz, $andy, '1/2-1/2');

    expect($yaz->record())->toBe(['wins' => 2, 'losses' => 1, 'draws' => 1])
        ->and($andy->record())->toBe(['wins' => 1, 'losses' => 2, 'draws' => 1]);
});

it('includes your record on /me', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();
    finishedGame($yaz, $andy, '1-0');

    $this->actingAs($yaz)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.record.wins', 1);
});

it('shows a friend\'s record, your head-to-head and your games together', function () {
    [$yaz, $andy, $other] = User::factory()->count(3)->create();
    $yaz->befriend($andy);
    finishedGame($yaz, $andy, '0-1');
    finishedGame($andy, $other, '1-0');

    $this->actingAs($yaz)->getJson("/api/v1/players/{$andy->username}")
        ->assertOk()
        ->assertJsonPath('data.record.wins', 2)
        ->assertJsonPath('data.head_to_head.losses', 1)
        ->assertJsonCount(1, 'data.games');
});

it('hides players who are not your friends with 404', function () {
    [$yaz, $stranger] = User::factory()->count(2)->create();

    $this->actingAs($yaz)->getJson("/api/v1/players/{$stranger->username}")->assertNotFound();
});

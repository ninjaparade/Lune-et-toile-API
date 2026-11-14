<?php

use App\Events\GameUpdated;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function friends(): array
{
    [$yaz, $andy] = User::factory()->count(2)->create();
    $yaz->befriend($andy);

    return [$yaz, $andy];
}

it('creates a game with a uid and invites a friend', function () {
    [$yaz, $andy] = friends();

    $response = $this->actingAs($yaz)->postJson('/api/v1/games', ['color' => 'white', 'friend_id' => $andy->id]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'waiting')
        ->assertJsonPath('data.white.id', $yaz->id)
        ->assertJsonPath('data.invited.id', $andy->id);
    expect($response->json('data.uid'))->toHaveLength(26);
});

it('only lets you invite friends', function () {
    [$yaz] = friends();
    $stranger = User::factory()->create();

    $this->actingAs($yaz)->postJson('/api/v1/games', ['color' => 'white', 'friend_id' => $stranger->id])
        ->assertUnprocessable()->assertJsonValidationErrors('friend_id');
});

it('starts the game when the invitee accepts', function () {
    [$yaz, $andy] = friends();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'invited_id' => $andy->id]);

    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.black.id', $andy->id)
        ->assertJsonPath('data.invited', null);
});

it('records moves in turn order and finishes with a result', function () {
    [$yaz, $andy] = friends();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => 'active']);

    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'e7e5', 'ply' => 0])->assertForbidden();
    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'f2f3', 'ply' => 0])->assertOk();
    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'g2g4', 'ply' => 0])->assertConflict();
    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'e7e5', 'ply' => 1])->assertOk();
    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'g2g4', 'ply' => 2])->assertOk();
    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/moves", [
        'uci' => 'd8h4', 'ply' => 3, 'result' => '0-1', 'termination' => 'checkmate',
    ])->assertOk()->assertJsonPath('data.status', 'finished')->assertJsonPath('data.result', '0-1');

    expect($game->fresh()->moves)->toBe(['f2f3', 'e7e5', 'g2g4', 'd8h4']);
});

it('hides games from people not in them with 404', function () {
    [$yaz, $andy] = friends();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id]);

    $this->actingAs($andy)->getJson("/api/v1/games/{$game->uid}")->assertNotFound();
});

it('lists games you play in or are invited to', function () {
    [$yaz, $andy] = friends();
    Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'invited_id' => $andy->id]);
    Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id]);

    $this->actingAs($andy)->getJson('/api/v1/games')->assertOk()->assertJsonCount(1, 'data');
});

it('resigning gives the win to the opponent', function () {
    [$yaz, $andy] = friends();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => 'active']);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/resign")
        ->assertOk()->assertJsonPath('data.result', '0-1')->assertJsonPath('data.termination', 'resignation');
});

it('broadcasts the updated game when a move is played', function () {
    Event::fake([GameUpdated::class]);
    [$yaz, $andy] = friends();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => 'active']);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'e2e4', 'ply' => 0])->assertOk();

    Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->game->moves === ['e2e4']);
});

it('only lets players join a game channel', function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => '1',
    ]);
    require base_path('routes/channels.php');
    [$yaz, $andy] = friends();
    $stranger = User::factory()->create();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => 'active']);

    $this->actingAs($stranger)->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => "presence-game.{$game->uid}"])
        ->assertForbidden();
    $this->actingAs($andy)->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => "presence-game.{$game->uid}"])
        ->assertOk()->assertJsonStructure(['auth', 'channel_data']);
});

<?php

use App\Models\DeviceToken;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->withoutDefer());

function nudgeGame(): array
{
    [$yaz, $andy] = User::factory()->count(2)->create();
    // White (yaz) has moved, so it's andy's turn.
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE, 'moves' => ['e2e4']]);

    return [$game, $yaz, $andy];
}

it('nudges the player to move with a generic notification, every 15 minutes', function () {
    withApnsKey();
    [$game, $yaz, $andy] = nudgeGame();
    DeviceToken::create(['user_id' => $andy->id, 'token' => str_repeat('cd', 32), 'environment' => 'sandbox']);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/nudge")->assertNoContent();
    Http::assertSent(fn (Request $request) => $request['aps']['alert']['body'] === "Friendly nudge — it's your move."
        && $request['game'] === $game->uid);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/nudge")
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'You can nudge again in 15 min.');
});

it('only nudges an opponent whose turn it is, in your own active game', function () {
    Http::fake();
    [$game, $yaz, $andy] = nudgeGame();

    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/nudge")->assertConflict();
    $this->actingAs(User::factory()->create())->postJson("/api/v1/games/{$game->uid}/nudge")->assertNotFound();

    $game->update(['status' => Game::FINISHED, 'result' => '1-0', 'termination' => 'resignation']);
    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/nudge")->assertConflict();
});

<?php

use App\Events\MessageSent;
use App\Models\DeviceToken;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function chatGame(): array
{
    [$yaz, $andy] = User::factory()->count(2)->create();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE]);

    return [$game, $yaz, $andy];
}

beforeEach(fn () => $this->withoutDefer());

it('sends a message, broadcasts it and lists the conversation oldest first', function () {
    Event::fake([MessageSent::class]);
    [$game, $yaz, $andy] = chatGame();

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/messages", ['body' => '  Good luck!  '])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Good luck!')
        ->assertJsonPath('data.user_id', $yaz->id);
    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/messages", ['body' => 'You too'])->assertCreated();

    Event::assertDispatched(MessageSent::class, 2);
    $this->actingAs($andy)->getJson("/api/v1/games/{$game->uid}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Good luck!')
        ->assertJsonPath('data.1.body', 'You too');
});

it('keeps chat between the two players', function () {
    [$game] = chatGame();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->getJson("/api/v1/games/{$game->uid}/messages")->assertNotFound();
    $this->actingAs($stranger)->postJson("/api/v1/games/{$game->uid}/messages", ['body' => 'hi'])->assertNotFound();
});

it('needs an opponent and a non-empty message', function () {
    $yaz = User::factory()->create();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id]);
    [$active, $white] = chatGame();

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/messages", ['body' => 'hello?'])->assertConflict();
    $this->actingAs($white)->postJson("/api/v1/games/{$active->uid}/messages", ['body' => ''])->assertUnprocessable();
    $this->actingAs($white)->postJson("/api/v1/games/{$active->uid}/messages", ['body' => str_repeat('a', 501)])->assertUnprocessable();
});

it('pushes a generic notice to the other player, without the message text', function () {
    withApnsKey();
    [$game, $yaz, $andy] = chatGame();
    DeviceToken::create(['user_id' => $andy->id, 'token' => str_repeat('cd', 32), 'environment' => 'sandbox']);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/messages", ['body' => 'secret plan'])->assertCreated();

    Http::assertSent(fn (Request $request) => $request['aps']['alert']['body'] === 'You have a new message.'
        && ! str_contains($request->body(), 'secret plan'));
});

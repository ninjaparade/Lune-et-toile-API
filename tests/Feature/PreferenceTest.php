<?php

use App\Models\Game;
use App\Models\User;

it('returns default preferences and saves changes on the account', function () {
    $yaz = User::factory()->create();

    $this->actingAs($yaz)->getJson('/api/v1/me')
        ->assertJsonPath('user.preferences', ['undo_seconds' => 0, 'piece_set' => 'rhosgfx']);

    $this->actingAs($yaz)->patchJson('/api/v1/me/preferences', ['undo_seconds' => 10])
        ->assertOk()->assertJsonPath('user.preferences', ['undo_seconds' => 10, 'piece_set' => 'rhosgfx']);
    $this->actingAs($yaz)->patchJson('/api/v1/me/preferences', ['piece_set' => 'celtic'])
        ->assertOk()->assertJsonPath('user.preferences', ['undo_seconds' => 10, 'piece_set' => 'celtic']);
});

it('rejects preferences outside the allowed values', function () {
    $this->actingAs(User::factory()->create())
        ->patchJson('/api/v1/me/preferences', ['undo_seconds' => 7, 'piece_set' => 'comic-sans'])
        ->assertUnprocessable()->assertJsonValidationErrors(['undo_seconds', 'piece_set']);
});

it('shows ads only when ads are switched on and the player is not ad-free', function () {
    $yaz = User::factory()->create();

    config(['etoile.ads_enabled' => false]);
    $this->actingAs($yaz)->getJson('/api/v1/me')->assertJsonPath('user.show_ads', false);

    config(['etoile.ads_enabled' => true]);
    $this->actingAs($yaz)->getJson('/api/v1/me')->assertJsonPath('user.show_ads', true);

    $this->artisan('users:ad-free', ['username' => $yaz->username])->assertSuccessful();
    $this->actingAs($yaz->fresh())->getJson('/api/v1/me')
        ->assertJsonPath('user.ad_free', true)->assertJsonPath('user.show_ads', false);
});

it('uses the configured nudge cooldown', function () {
    config(['etoile.nudge_cooldown_minutes' => 2]);
    $this->freezeSecond();
    [$yaz, $andy] = User::factory()->count(2)->create();
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE]);
    $game->forceFill(['white_nudged_at' => now()])->save();

    expect($game->nextNudgeAt('white')?->equalTo(now()->addMinutes(2)))->toBeTrue();
    $this->travel(2)->minutes();
    expect($game->fresh()->nextNudgeAt('white'))->toBeNull();
});

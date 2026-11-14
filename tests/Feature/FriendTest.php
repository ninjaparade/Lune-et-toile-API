<?php

use App\Models\User;

it('adds a friend by username for both players', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();

    $this->actingAs($yaz)->postJson('/api/v1/friends', ['username' => $andy->username])
        ->assertCreated()->assertJsonPath('data.username', $andy->username);

    expect($yaz->isFriendsWith($andy))->toBeTrue()
        ->and($andy->isFriendsWith($yaz))->toBeTrue();
});

it('rejects an unknown username with 422', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/v1/friends', ['username' => 'ghost'])
        ->assertUnprocessable()->assertJsonValidationErrors('username');
});

it('lists and removes friends', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();
    $yaz->befriend($andy);

    $this->actingAs($yaz)->getJson('/api/v1/friends')->assertOk()->assertJsonPath('data.0.id', $andy->id);
    $this->actingAs($yaz)->deleteJson("/api/v1/friends/{$andy->id}")->assertNoContent();

    expect($andy->isFriendsWith($yaz))->toBeFalse();
});

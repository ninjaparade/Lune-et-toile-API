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

it('gives every player a shareable uid and adds friends by it', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();
    expect($andy->uid)->toHaveLength(26);

    $this->actingAs($andy)->getJson('/api/v1/me')->assertJsonPath('user.uid', $andy->uid);

    $this->actingAs($yaz)->postJson('/api/v1/friends', ['uid' => strtoupper($andy->uid)])
        ->assertCreated()->assertJsonPath('data.uid', $andy->uid);

    expect($yaz->isFriendsWith($andy))->toBeTrue();
});

it('rejects an unknown or own uid with 422', function () {
    $yaz = User::factory()->create();

    $this->actingAs($yaz)->postJson('/api/v1/friends', ['uid' => '01kzzzzzzzzzzzzzzzzzzzzzzz'])
        ->assertUnprocessable()->assertJsonValidationErrors('uid');
    $this->actingAs($yaz)->postJson('/api/v1/friends', ['uid' => $yaz->uid])
        ->assertUnprocessable()->assertJsonValidationErrors('uid');
    $this->actingAs($yaz)->postJson('/api/v1/friends', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['username', 'uid']);
});

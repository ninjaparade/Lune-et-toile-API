<?php

use App\Models\User;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'White Player',
        'username' => 'white',
        'email' => 'White@Example.com',
        'password' => 'password123',
        'device_name' => 'iPhone',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.username', 'white')
        ->assertJsonPath('user.email', 'white@example.com');
    $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.username', 'white');
});

it('rejects a taken username with 422', function () {
    User::factory()->create(['username' => 'white']);

    $this->postJson('/api/v1/register', [
        'name' => 'New', 'username' => 'white', 'email' => 'new@example.com', 'password' => 'password123', 'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonValidationErrors('username');
});

it('logs in with email and password', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'username', 'email']]);
});

it('rejects a wrong password with 422', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'nope', 'device_name' => 'iPhone'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('returns 401 from /me without a token', function () {
    $this->get('/api/v1/me')->assertUnauthorized();
});

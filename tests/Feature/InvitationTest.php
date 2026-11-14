<?php

use App\Mail\InviteMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('emails an invite to someone without an account', function () {
    $yaz = User::factory()->create();

    $this->actingAs($yaz)->postJson('/api/v1/invitations', ['email' => 'New.Person@Example.com'])
        ->assertAccepted()
        ->assertJsonPath('message', 'Invite sent to new.person@example.com.');

    expect(Invitation::where('email', 'new.person@example.com')->where('inviter_id', $yaz->id)->exists())->toBeTrue();
    Mail::assertSent(InviteMail::class, fn (InviteMail $mail) => $mail->hasTo('new.person@example.com') && ! $mail->hasAccount);
});

it('makes you friends when the invited person signs up with that email', function () {
    $yaz = User::factory()->create();
    $this->actingAs($yaz)->postJson('/api/v1/invitations', ['email' => 'andy@example.com'])->assertAccepted();

    $this->postJson('/api/v1/register', [
        'name' => 'Andy', 'username' => 'andy', 'email' => 'Andy@example.com',
        'password' => 'password', 'device_name' => 'test',
    ])->assertCreated();

    expect($yaz->fresh()->isFriendsWith(User::where('username', 'andy')->first()))->toBeTrue()
        ->and(Invitation::count())->toBe(0);
});

it('adds an existing player as a friend with the same response', function () {
    [$yaz, $andy] = User::factory()->count(2)->create();

    $this->actingAs($yaz)->postJson('/api/v1/invitations', ['email' => $andy->email])
        ->assertAccepted()
        ->assertJsonPath('message', "Invite sent to {$andy->email}.");

    expect($yaz->isFriendsWith($andy))->toBeTrue()->and(Invitation::count())->toBe(0);
    Mail::assertSent(InviteMail::class, fn (InviteMail $mail) => $mail->hasAccount);
});

it('rejects inviting yourself', function () {
    $yaz = User::factory()->create();

    $this->actingAs($yaz)->postJson('/api/v1/invitations', ['email' => $yaz->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

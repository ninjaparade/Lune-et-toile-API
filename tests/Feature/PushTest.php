<?php

use App\Models\DeviceToken;
use App\Models\Game;
use App\Models\User;
use App\Support\GamePush;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function pushPlayers(): array
{
    [$yaz, $andy] = User::factory()->count(2)->sequence(['name' => 'Yaz Jallad'], ['name' => 'Andrea Dunn'])->create();
    $yaz->befriend($andy);

    return [$yaz, $andy];
}

function withApnsKey(int $status = 200, array $body = []): void
{
    Http::fake(['*.push.apple.com/*' => Http::response($body, $status)]);

    // A throwaway P-256 key: Apple is faked, we only need valid ES256 signing.
    openssl_pkey_export(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]), $pem);
    config(['services.apn.key_base64' => base64_encode($pem), 'services.apn.key_id' => 'TESTKEY123', 'services.apn.key_path' => null]);
}

beforeEach(fn () => $this->withoutDefer());

it('registers a device token and moves it to whoever signs in last', function () {
    Http::fake();
    [$yaz, $andy] = pushPlayers();
    $token = str_repeat('ab', 32);

    $this->actingAs($yaz)->postJson('/api/v1/devices', ['token' => $token, 'environment' => 'sandbox'])->assertNoContent();
    $this->actingAs($andy)->postJson('/api/v1/devices', ['token' => $token, 'environment' => 'sandbox'])->assertNoContent();

    expect(DeviceToken::sole()->user_id)->toBe($andy->id);

    $this->actingAs($andy)->deleteJson("/api/v1/devices/{$token}")->assertNoContent();
    expect(DeviceToken::count())->toBe(0);
});

it('tells the opponent it is their move, on the host for their build', function () {
    withApnsKey();
    [$yaz, $andy] = pushPlayers();
    $andy->deviceTokens()->create(['token' => str_repeat('cd', 32), 'environment' => 'production']);
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE]);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'e2e4', 'ply' => 0, 'san' => 'e4'])->assertOk();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.push.apple.com/3/device/'.str_repeat('cd', 32))
        && $request['aps']['alert'] == ['title' => 'vs Yaz Jallad', 'body' => 'Yaz moved a pawn. Your move.']
        && $request['aps']['badge'] === 1
        && $request['game'] === $game->uid
        && $request->header('apns-topic') === ['com.ninjaparade.etoile']);
});

it('words checkmate and resignation for the person receiving them', function () {
    withApnsKey();
    [$yaz, $andy] = pushPlayers();
    $yaz->deviceTokens()->create(['token' => str_repeat('ef', 32), 'environment' => 'sandbox']);
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE, 'moves' => ['f2f3', 'e7e5', 'g2g4']]);

    $this->actingAs($andy)->postJson("/api/v1/games/{$game->uid}/moves", [
        'uci' => 'd8h4', 'ply' => 3, 'san' => 'Qh4#', 'result' => '0-1', 'termination' => 'checkmate',
    ])->assertOk();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.sandbox.push.apple.com/')
        && $request['aps']['alert']['body'] === 'Andrea moved a queen. Checkmate — you lost this one.');
});

it('notifies the invitee of a new game', function () {
    withApnsKey();
    [$yaz, $andy] = pushPlayers();
    $andy->deviceTokens()->create(['token' => str_repeat('12', 32), 'environment' => 'sandbox']);

    $this->actingAs($yaz)->postJson('/api/v1/games', ['color' => 'white', 'friend_id' => $andy->id])->assertCreated();

    Http::assertSent(fn (Request $request) => $request['aps']['alert'] == ['title' => 'Yaz Jallad', 'body' => "Invited you to a game — you'd play Black."]
        && $request['aps']['badge'] === 1);
});

it('drops tokens Apple says are dead', function () {
    withApnsKey(410, ['reason' => 'Unregistered']);
    [$yaz, $andy] = pushPlayers();
    $andy->deviceTokens()->create(['token' => str_repeat('34', 32), 'environment' => 'sandbox']);
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE]);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/resign")->assertOk();

    expect(DeviceToken::count())->toBe(0);
});

it('sends nothing until a key is configured', function () {
    Http::fake();
    [$yaz, $andy] = pushPlayers();
    $andy->deviceTokens()->create(['token' => str_repeat('56', 32), 'environment' => 'sandbox']);
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE]);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'e2e4', 'ply' => 0])->assertOk();

    Http::assertNothingSent();
});

it('describes moves in plain English', function (string $san, ?string $captured, string $expected) {
    expect(GamePush::describe('Yaz', $san, $captured))->toBe($expected);
})->with([
    ['Nf3', null, 'Yaz moved a knight'],
    ['exd5', 'pawn', "Yaz's pawn took your pawn"],
    ['Bxf7+', 'rook', "Yaz's bishop took your rook"],
    ['O-O', null, 'Yaz castled kingside'],
    ['O-O-O+', null, 'Yaz castled queenside'],
    ['e8=Q', null, 'Yaz moved a pawn and promoted it to a queen'],
    ['dxc8=N', 'bishop', "Yaz's pawn took your bishop and promoted it to a knight"],
]);

it('says check when the move gives check', function () {
    withApnsKey();
    [$yaz, $andy] = pushPlayers();
    $andy->deviceTokens()->create(['token' => str_repeat('78', 32), 'environment' => 'sandbox']);
    $game = Game::create(['created_by' => $yaz->id, 'white_id' => $yaz->id, 'black_id' => $andy->id, 'status' => Game::ACTIVE, 'moves' => ['e2e4', 'f7f6']]);

    $this->actingAs($yaz)->postJson("/api/v1/games/{$game->uid}/moves", ['uci' => 'd1h5', 'ply' => 2, 'san' => 'Qh5+'])->assertOk();

    Http::assertSent(fn (Request $request) => $request['aps']['alert']['body'] === 'Yaz moved a queen. Check — your move.');
});

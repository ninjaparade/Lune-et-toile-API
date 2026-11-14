<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal APNs client (token auth over HTTP/2) — no SDK needed. Each device token is sent to
 * the host for its own environment. Quietly does nothing until a key is configured.
 */
class Apns
{
    private const HOSTS = [
        'sandbox' => 'https://api.sandbox.push.apple.com',
        'production' => 'https://api.push.apple.com',
    ];

    public function isConfigured(): bool
    {
        return $this->privateKey() !== null && filled(config('services.apn.key_id'));
    }

    /**
     * Alert every device the user is signed in on. `$data` rides along in the payload
     * (e.g. the game uid the app opens when the notification is tapped).
     */
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $payload = [...$data, 'aps' => [
            'alert' => ['title' => $title, 'body' => $body],
            'sound' => 'default',
            'badge' => $user->pendingCount(),
            'thread-id' => $data['game'] ?? 'etoile',
        ]];

        foreach ($user->deviceTokens as $device) {
            $this->deliver($device, $payload);
        }
    }

    private function deliver(DeviceToken $device, array $payload): void
    {
        try {
            $response = Http::withOptions(['version' => 2.0])
                ->withToken($this->providerToken(), 'bearer')
                ->withHeaders([
                    'apns-topic' => config('services.apn.bundle_id'),
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                ])
                ->post(self::HOSTS[$device->environment]."/3/device/{$device->token}", $payload);

            // Apple says the token is dead (app deleted, or never valid for this host): stop using it.
            if ($response->status() === 410 || in_array($response->json('reason'), ['BadDeviceToken', 'Unregistered'], true)) {
                $device->delete();
            } elseif ($response->failed()) {
                Log::warning('APNs push failed', ['status' => $response->status(), 'reason' => $response->json('reason')]);
            }
        } catch (\Throwable $e) {
            Log::warning('APNs push exception: '.$e->getMessage());
        }
    }

    private function privateKey(): ?string
    {
        if ($path = config('services.apn.key_path')) {
            $path = str_starts_with($path, '/') ? $path : base_path($path);

            return is_readable($path) ? file_get_contents($path) : null;
        }

        $base64 = config('services.apn.key_base64');

        return $base64 ? (base64_decode($base64, true) ?: null) : null;
    }

    /**
     * ES256 JWT. Apple wants it reused for 20–60 minutes rather than minted per request.
     */
    private function providerToken(): string
    {
        $keyId = config('services.apn.key_id');

        return Cache::remember("apns.provider-token.{$keyId}", now()->addMinutes(45), function () use ($keyId) {
            $encode = fn (string $raw) => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            $input = $encode(json_encode(['alg' => 'ES256', 'kid' => $keyId]))
                .'.'.$encode(json_encode(['iss' => config('services.apn.team_id'), 'iat' => time()]));

            openssl_sign($input, $der, openssl_pkey_get_private($this->privateKey()), OPENSSL_ALGO_SHA256);

            return $input.'.'.$encode($this->derToRaw($der));
        });
    }

    /**
     * openssl gives an ASN.1 DER ECDSA signature; JWT ES256 wants raw r‖s (2 × 32 bytes).
     */
    private function derToRaw(string $der): string
    {
        $raw = '';
        $pos = 2;
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$pos + 1]);
            $raw .= str_pad(ltrim(substr($der, $pos + 2, $length), "\x00"), 32, "\x00", STR_PAD_LEFT);
            $pos += 2 + $length;
        }

        return $raw;
    }
}

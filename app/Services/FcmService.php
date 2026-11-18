<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through Firebase Cloud Messaging (HTTP v1).
 * Needs a service-account JSON: set FCM_CREDENTIALS to its path (keep it outside git/public).
 * When not configured, sending is silently skipped so the API keeps working.
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function enabled(): bool
    {
        $path = config('services.fcm.credentials');

        return $path && is_file($path);
    }

    /** Push to every device of a user. $data values are cast to strings (FCM requirement). */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $credentials = $this->credentials();
        $accessToken = $this->accessToken($credentials);
        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        $data = array_map('strval', array_filter($data, fn ($v) => $v !== null));

        foreach ($tokens as $device) {
            $response = Http::withToken($accessToken)->timeout(8)->post($url, ['message' => [
                'token' => $device->token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $data,
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'ride_default']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]]);

            if ($response->successful()) {
                continue;
            }

            $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
            if ($response->status() === 404 || $status === 'UNREGISTERED') {
                $device->delete(); // app uninstalled / token rotated
            } else {
                Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
    }

    private function credentials(): array
    {
        return json_decode(file_get_contents(config('services.fcm.credentials')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** OAuth2 access token from a signed JWT, cached for ~55 minutes. */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token', 3300, function () use ($credentials) {
            $now = time();
            $segments = [
                $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->b64(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $segments[] = $this->b64($signature);

            $response = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw();

            return $response->json('access_token');
        });
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

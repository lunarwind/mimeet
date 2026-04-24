<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->loadCredentials();

        if (!$credentials) {
            if (config('app.env') !== 'production') {
                Log::info('[FCM STUB]', compact('token', 'title', 'body', 'data'));
                return true;
            }
            Log::warning('[FCM] No Firebase credentials configured. Set FIREBASE_CREDENTIALS_PATH or FIREBASE_CREDENTIALS_JSON.');
            return false;
        }

        $accessToken = $this->getAccessToken($credentials);
        if (!$accessToken) {
            return false;
        }

        $projectId = $credentials['project_id'] ?? '';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::withToken($accessToken)->post($url, [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
            ],
        ]);

        if ($response->status() === 404) {
            FcmToken::where('token', $token)->delete();
            Log::info('[FCM] Invalid token removed', ['token' => substr($token, 0, 20) . '...']);
            return false;
        }

        if (!$response->successful()) {
            Log::error('[FCM] Send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Send to multiple tokens in one batch (uses FCM HTTP v1 individual sends).
     * Returns ['success' => N, 'failure' => N].
     */
    public function sendMulticast(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        if (config('app.env') !== 'production') {
            Log::info('[FCM STUB multicast]', ['count' => count($tokens), 'title' => $title]);
            return ['success' => count($tokens), 'failure' => 0];
        }

        $credentials = $this->loadCredentials();
        if (!$credentials) {
            Log::warning('[FCM] No credentials configured for multicast.');
            return ['success' => 0, 'failure' => count($tokens)];
        }

        $accessToken = $this->getAccessToken($credentials);
        if (!$accessToken) {
            return ['success' => 0, 'failure' => count($tokens)];
        }

        $success = 0;
        $failure = 0;
        $invalidTokens = [];
        $projectId = $credentials['project_id'] ?? '';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                ],
            ]);

            if ($response->status() === 404) {
                $invalidTokens[] = $token;
                $failure++;
            } elseif ($response->successful()) {
                $success++;
            } else {
                $failure++;
            }
        }

        if (!empty($invalidTokens)) {
            FcmToken::whereIn('token', $invalidTokens)->delete();
            Log::info('[FCM] Removed invalid tokens', ['count' => count($invalidTokens)]);
        }

        return compact('success', 'failure');
    }

    private function loadCredentials(): ?array
    {
        $path = config('services.fcm.credentials_path');
        $json = config('services.fcm.credentials_json');

        if ($path && file_exists($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            return is_array($decoded) ? $decoded : null;
        }

        if ($json) {
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function getAccessToken(array $credentials): ?string
    {
        return Cache::remember('fcm_access_token', 3500, fn () => $this->fetchAccessToken($credentials));
    }

    private function fetchAccessToken(array $credentials): ?string
    {
        $now = time();
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";

        $privateKey = openssl_pkey_get_private($credentials['private_key'] ?? '');
        if (!$privateKey) {
            Log::error('[FCM] Invalid or missing private_key in credentials');
            return null;
        }

        openssl_sign($signingInput, $signature, $privateKey, 'sha256WithRSAEncryption');
        $jwt = "{$signingInput}." . $this->base64url($signature);

        $response = Http::asForm()->post(
            $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]
        );

        if (!$response->successful()) {
            Log::error('[FCM] Failed to fetch access token', ['body' => $response->body()]);
            return null;
        }

        return $response->json('access_token');
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

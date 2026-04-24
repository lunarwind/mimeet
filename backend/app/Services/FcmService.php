<?php

namespace App\Services;

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

        if (!$response->successful()) {
            Log::error('[FCM] Send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
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

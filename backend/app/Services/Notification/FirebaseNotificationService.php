<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\User;
use App\UserDeviceToken;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    private string $projectId;
    private ?string $credentialsPath;
    private ?string $cachedAccessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', env('FIREBASE_PROJECT_ID', 'funmoments-project'));
        $this->credentialsPath = config('services.firebase.credentials', env('FIREBASE_CREDENTIALS'));
    }

    /**
     * Send push notification to all active devices registered for a user.
     * Guaranteed fail-safe: never throws exceptions that could block order/payment transactions.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        try {
            $deviceTokens = $user->deviceTokens()->pluck('device_token')->filter()->unique()->toArray();
            if (empty($deviceTokens)) {
                Log::info("[FCM] User #{$user->id} has no registered device tokens; skipping push.");
                return ['success' => true, 'sent_count' => 0, 'skipped' => true];
            }

            return $this->sendToTokens($deviceTokens, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::error("[FCM] Non-blocking error sending to user #{$user->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification to multiple device tokens via FCM HTTP v1.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::info("[FCM] Firebase credentials not configured in environment; push simulated/skipped safely.");
            return ['success' => false, 'reason' => 'FCM_NOT_CONFIGURED', 'sent_count' => 0];
        }

        $sentCount = 0;
        $invalidTokens = [];

        // Cast all data values to string as required by FCM v1 data specification
        $stringData = [];
        foreach ($data as $key => $val) {
            $stringData[(string) $key] = is_null($val) ? '' : (string) $val;
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'fun_moments_booking_channel',
                            'sound' => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                ],
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $sentCount++;
                } else {
                    $status = $response->status();
                    $bodyJson = $response->json();
                    $errorCode = $bodyJson['error']['details'][0]['errorCode'] ?? $bodyJson['error']['status'] ?? '';

                    Log::warning("[FCM] Failed sending to token ({$status}): {$response->body()}");

                    // Prune unregistered/invalid tokens
                    if ($status === 404 || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'])) {
                        $invalidTokens[] = $token;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("[FCM] Exception sending to token: " . $e->getMessage());
            }
        }

        // Clean up invalid tokens automatically
        if (!empty($invalidTokens)) {
            UserDeviceToken::whereIn('device_token', $invalidTokens)->delete();
            Log::info('[FCM] Pruned ' . count($invalidTokens) . ' invalid/expired device tokens.');
        }

        return [
            'success' => true,
            'sent_count' => $sentCount,
            'pruned_count' => count($invalidTokens),
        ];
    }

    /**
     * Obtain OAuth2 access token for Firebase Cloud Messaging HTTP v1 using Service Account.
     */
    private function getAccessToken(): ?string
    {
        if ($this->cachedAccessToken && time() < $this->accessTokenExpiresAt - 60) {
            return $this->cachedAccessToken;
        }

        if (empty($this->credentialsPath) || !file_exists($this->credentialsPath)) {
            // Check default paths if not explicitly configured
            $defaultPaths = [
                storage_path('app/firebase_credentials.json'),
                base_path('firebase_credentials.json'),
            ];
            foreach ($defaultPaths as $path) {
                if (file_exists($path)) {
                    $this->credentialsPath = $path;
                    break;
                }
            }
        }

        if (empty($this->credentialsPath) || !file_exists($this->credentialsPath)) {
            return null;
        }

        try {
            $json = json_decode((string) file_get_contents($this->credentialsPath), true);
            if (!isset($json['client_email'], $json['private_key'])) {
                return null;
            }

            $now = time();
            $jwtPayload = [
                'iss' => $json['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = JWT::encode($jwtPayload, $json['private_key'], 'RS256');

            $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($res->successful()) {
                $data = $res->json();
                $this->cachedAccessToken = $data['access_token'];
                $this->accessTokenExpiresAt = $now + (int) ($data['expires_in'] ?? 3600);
                return $this->cachedAccessToken;
            }

            Log::error('[FCM] Failed obtaining OAuth2 token: ' . $res->body());
            return null;
        } catch (\Throwable $e) {
            Log::error('[FCM] Error generating OAuth2 token from service account: ' . $e->getMessage());
            return null;
        }
    }
}

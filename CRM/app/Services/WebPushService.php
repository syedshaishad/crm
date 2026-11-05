<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    private ?array $keys = null;

    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    public function keys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $public = (string) config('webpush.public_key');
        $private = (string) config('webpush.private_key');
        if ($public !== '' && $private !== '') {
            return $this->keys = ['publicKey' => $public, 'privateKey' => $private];
        }

        $file = (string) config('webpush.vapid_file');
        if (is_file($file)) {
            $saved = json_decode((string) file_get_contents($file), true);
            if (is_array($saved) && ! empty($saved['publicKey']) && ! empty($saved['privateKey'])) {
                return $this->keys = ['publicKey' => $saved['publicKey'], 'privateKey' => $saved['privateKey']];
            }
        }

        $generated = $this->generateKeys();
        $dir = dirname($file);
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create the Web Push key directory.');
        }
        if (@file_put_contents($file, json_encode($generated), LOCK_EX) === false) {
            throw new \RuntimeException('Could not save the Web Push VAPID keys.');
        }
        @chmod($file, 0600);

        return $this->keys = $generated;
    }

    private function generateKeys(): array
    {
        try {
            return VAPID::createVapidKeys();
        } catch (Throwable $primary) {
            $config = $this->opensslConfig();
            if ($config === null) {
                throw $primary;
            }

            $key = @openssl_pkey_new([
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name' => 'prime256v1',
                'config' => $config,
            ]);
            $details = $key ? openssl_pkey_get_details($key) : false;
            $x = $details['ec']['x'] ?? null;
            $y = $details['ec']['y'] ?? null;
            $d = $details['ec']['d'] ?? null;
            while (openssl_error_string()) {
            }

            if (! is_string($x) || ! is_string($y) || ! is_string($d) || strlen($x) !== 32 || strlen($y) !== 32 || strlen($d) !== 32) {
                throw $primary;
            }

            return [
                'publicKey' => $this->base64Url("\x04".$x.$y),
                'privateKey' => $this->base64Url($d),
            ];
        }
    }

    private function opensslConfig(): ?string
    {
        $candidates = [
            config('webpush.openssl_conf'),
            getenv('OPENSSL_CONF') ?: null,
            'C:\\Program Files\\Common Files\\SSL\\openssl.cnf',
            'C:\\Program Files\\Git\\usr\\ssl\\openssl.cnf',
            'C:\\Program Files\\Git\\mingw64\\ssl\\openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public function send(User $user, array $payload): array
    {
        $subscriptions = $user->pushSubscriptions()->get();
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        try {
            $keys = $this->keys();
            $clientOptions = [
                'timeout' => max(2, (int) config('webpush.timeout', 8)),
                'connect_timeout' => 4,
            ];
            $caBundle = config('webpush.ca_bundle');
            if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
                $clientOptions['verify'] = $caBundle;
            }
            $webPush = new WebPush(
                ['VAPID' => ['subject' => config('webpush.subject'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]],
                ['TTL' => 3600, 'urgency' => 'high'],
                new Client($clientOptions)
            );
        } catch (Throwable $e) {
            Log::warning('Web push could not start: '.$e->getMessage());

            return $result;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $result;
        }

        $queued = [];
        foreach ($subscriptions as $subscription) {
            try {
                $encoding = in_array($subscription->content_encoding, ['aes128gcm', 'aesgcm'], true)
                    ? $subscription->content_encoding : 'aes128gcm';
                $webPush->queueNotification(
                    new Subscription(
                        $subscription->endpoint,
                        $subscription->public_key,
                        $subscription->auth_token,
                        $encoding
                    ),
                    $json
                );
                $queued[hash('sha256', $subscription->endpoint)] = $subscription;
            } catch (Throwable $e) {
                $result['failed']++;
                Log::warning('Web push subscription was skipped: '.$e->getMessage());
            }
        }

        if ($queued === []) {
            return $result;
        }

        try {
            foreach ($webPush->flush() as $report) {
                $subscription = $queued[hash('sha256', $report->getEndpoint())] ?? null;

                if ($report->isSuccess()) {
                    $result['sent']++;
                    $subscription?->forceFill(['last_used_at' => now()])->save();
                    continue;
                }

                $result['failed']++;
                if ($report->isSubscriptionExpired() && $subscription) {
                    $subscription->delete();
                    $result['removed']++;
                } else {
                    $status = $report->getResponse()?->getStatusCode();
                    Log::warning('Web push delivery failed: '.$report->getReason().($status ? ' (HTTP '.$status.')' : ''));
                }
            }
        } catch (Throwable $e) {
            $result['failed'] += count($queued);
            Log::warning('Web push delivery failed: '.$e->getMessage());
        }

        return $result;
    }

    public function sendTest(User $user): array
    {
        return $this->send($user, [
            'title' => 'Notifications are on',
            'body' => 'Sound and profile picture are ready on this device.',
            'url' => route('settings.account'),
            'icon' => $user->avatarUrl() ?: asset('favicon.ico'),
            'badge' => asset('favicon.ico'),
            'tag' => 'crm-test-'.now()->timestamp,
            'vibrate' => [120, 60, 120],
        ]);
    }
}

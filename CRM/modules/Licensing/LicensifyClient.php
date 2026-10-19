<?php

namespace Modules\Licensing;

use Illuminate\Support\Facades\Http;

/** Transport failure / HTTP error talking to the Licensify server. */
class LicensifyException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $code2 = null,
        public readonly ?string $requestId = null,
        public readonly int $httpStatus = 0,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}

/**
 * Minimal Licensify v1 client (HMAC request signing, Scheme A).
 *
 * Docs: https://license.shaishad.com/docs/print
 * Every request proves the calling product (API key + HMAC) and names the
 * license under test in the body. Refused licenses answer HTTP 200 with
 * success:false — only transport/auth failures throw.
 */
class LicensifyClient
{
    private int $skew = 0;

    public function __construct(
        private readonly string $serverUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly int $timeout = 10,
        private readonly bool $verifySsl = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('licensing.server_url'),
            (string) config('licensing.api_key'),
            (string) config('licensing.api_secret'),
            (int) config('licensing.timeout', 10),
            (bool) config('licensing.verify_ssl', true),
        );
    }

    public function configured(): bool
    {
        return $this->serverUrl !== '' && $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /** Canonical request string: exactly six LF-joined lines, no trailing newline. */
    public function canonical(string $method, string $path, string $query, string $bodySha256, string $timestamp, string $nonce): string
    {
        return implode("\n", [$method, $path, $query, $bodySha256, $timestamp, $nonce]);
    }

    public static function canonicalQuery(array $params): string
    {
        if (! $params) {
            return '';
        }
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = rawurlencode((string) $k).'='.rawurlencode((string) $v);
        }
        sort($pairs);

        return implode('&', $pairs);
    }

    public static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function signature(string $canonical): string
    {
        return self::base64url(hash_hmac('sha256', $canonical, $this->apiSecret, true));
    }

    /** GET /ping — liveness + server clock (no auth). */
    public function ping(): array
    {
        return $this->send('GET', '/ping');
    }

    /** GET /products/{code}/public-key — signing keyring (no auth). */
    public function publicKey(string $productCode): array
    {
        return $this->send('GET', '/products/'.rawurlencode($productCode).'/public-key');
    }

    public function activate(array $payload): array
    {
        return $this->send('POST', '/license/activate', $payload);
    }

    public function validate(array $payload): array
    {
        return $this->send('POST', '/license/validate', $payload);
    }

    public function deactivate(array $payload): array
    {
        return $this->send('POST', '/license/deactivate', $payload);
    }

    public function status(array $payload): array
    {
        return $this->send('POST', '/license/status', $payload);
    }

    public function checkUpdate(array $payload): array
    {
        return $this->send('POST', '/updates/check', $payload);
    }

    protected function send(string $method, string $path, array $body = [], array $query = [], bool $retried = false): array
    {
        $base = rtrim($this->serverUrl, '/').'/api/v1';
        $json = $method === 'GET' ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $qs = self::canonicalQuery($query);
        $ts = (string) (time() + $this->skew);
        $nonce = self::base64url(random_bytes(18));
        $canonical = $this->canonical($method, '/api/v1'.$path, $qs, hash('sha256', $json), $ts, $nonce);
        $url = $base.$path.($qs !== '' ? '?'.$qs : '');
        try {
            $pending = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-License-Key' => $this->apiKey,
                    'X-License-Timestamp' => $ts,
                    'X-License-Nonce' => $nonce,
                    'X-License-Signature' => $this->signature($canonical),
                    'Accept' => 'application/json',
                ]);
            if (! $this->verifySsl) {
                $pending = $pending->withoutVerifying();
            }
            $res = $method === 'GET' ? $pending->get($url) : $pending->withBody($json, 'application/json')->post($url);
        } catch (\Throwable $e) {
            throw new LicensifyException('License server unreachable: '.$e->getMessage());
        }
        $data = null;
        try {
            $data = $res->json();
        } catch (\Throwable $e) {
        }
        if (! is_array($data)) {
            throw new LicensifyException('License server returned a non-JSON response (HTTP '.$res->status().').', null, null, $res->status());
        }
        // Clock skew: measure against /ping once, then replay the request.
        $code = $data['error']['code'] ?? $data['error_code'] ?? null;
        if ($code === 'STALE_REQUEST' && ! $retried) {
            $this->syncClock();
            return $this->send($method, $path, $body, $query, true);
        }
        if ($res->status() === 429) {
            throw new LicensifyException(
                'License server rate limit hit. Try again shortly.',
                'RATE_LIMIT_EXCEEDED',
                $data['meta']['request_id'] ?? null,
                429,
                is_numeric($res->header('Retry-After')) ? (int) $res->header('Retry-After') : null,
            );
        }
        if ($res->status() >= 400) {
            throw new LicensifyException(
                (string) ($data['error']['message'] ?? 'License server error (HTTP '.$res->status().').'),
                isset($data['error']['code']) ? (string) $data['error']['code'] : null,
                $data['meta']['request_id'] ?? null,
                $res->status(),
            );
        }

        return $data;
    }

    /** Measure clock offset against the server (for replay-window skew). */
    protected function syncClock(): void
    {
        try {
            $data = $this->send('GET', '/ping', [], [], true);
            $server = $data['server_time'] ?? $data['time'] ?? $data['now'] ?? null;
            if ($server !== null) {
                $ts = is_numeric($server) ? (int) $server : (int) strtotime((string) $server);
                if ($ts > 0) {
                    $this->skew = $ts - time();
                }
            }
        } catch (\Throwable $e) {
        }
    }
}

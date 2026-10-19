<?php

namespace Modules\Licensing;

/**
 * Offline certificate trust: compact Ed25519 tokens minted by the Licensify
 * server (header.payload.signature, base64url). The private key never leaves
 * the server, so this install can only VERIFY — never mint. Deleting the
 * cached certificate forces an immediate server re-validation; offline, that
 * fails, and the app stays blocked. Fail-closed by construction.
 */
class LicenseCertificate
{
    /** Split a compact token into [header, payload, signature] arrays/bytes. */
    public static function split(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64dec($h), true);
        $payload = json_decode(self::b64dec($p), true);
        $sig = self::b64dec($s, true);
        if (! is_array($header) || ! is_array($payload) || $sig === '') {
            return null;
        }

        return [$header, $payload, $h.'.'.$p, $sig];
    }

    /**
     * Verify token against [kid => raw-32-byte-key]. Returns
     * [true, claims] or [false, reason]. Enforces: signature, exp/nbf window,
     * product + license binding (when the claims carry them).
     *
     * $leeway (seconds) absorbs server/client clock drift on both edges of
     * the window. Without it, a server clock minutes ahead of this machine
     * mints certificates whose nbf is still in our future, and every
     * revalidation fails even though the license is valid.
     */
    public static function verify(string $token, array $keys, ?string $productCode = null, ?string $licenseKey = null, ?int $now = null, int $leeway = 300): array
    {
        if (! function_exists('sodium_crypto_sign_verify_detached') || ! extension_loaded('sodium')) {
            return [false, 'PHP ext-sodium is missing — certificates cannot be verified on this server.'];
        }
        $split = self::split($token);
        if (! $split) {
            return [false, 'Malformed certificate token.'];
        }
        [$header, $claims, $signed, $sig] = $split;
        $alg = strtolower((string) ($header['alg'] ?? 'ed25519'));
        if ($alg !== 'ed25519' && $alg !== 'eddsa') {
            return [false, 'Unsupported certificate algorithm ('.($header['alg'] ?? '?').').'];
        }
        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '' || ! array_key_exists($kid, $keys)) {
            return [false, 'Unknown signing key — refresh the keyring.'];
        }
        $key = $keys[$kid];
        if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return [false, 'Bad keyring entry for this certificate.'];
        }
        try {
            $ok = sodium_crypto_sign_verify_detached($sig, $signed, $key);
        } catch (\Throwable $e) {
            return [false, 'Certificate signature check failed.'];
        }
        if (! $ok) {
            return [false, 'Certificate signature does not verify.'];
        }
        $now = $now ?? time();
        $leeway = max(0, $leeway);
        foreach (['exp', 'offline_until', 'expires_at'] as $k) {
            if (isset($claims[$k]) && is_numeric($t = self::toTs($claims[$k])) && $t > 0 && $now > $t + $leeway) {
                return [false, 'Certificate window has closed.'];
            }
        }
        foreach (['nbf', 'not_before'] as $k) {
            if (isset($claims[$k]) && is_numeric($t = self::toTs($claims[$k])) && $t > 0 && $now < $t - $leeway) {
                return [false, 'Certificate is not yet valid (clock skew?).'];
            }
        }
        if ($productCode !== null) {
            $p = $claims['product'] ?? $claims['product_code'] ?? $claims['pcode'] ?? $claims['pid'] ?? null;
            if ($p !== null && (string) $p !== (string) $productCode) {
                return [false, 'Certificate is for a different product.'];
            }
        }
        if ($licenseKey !== null) {
            $l = $claims['license'] ?? $claims['license_key'] ?? $claims['license_id'] ?? $claims['lid'] ?? null;
            if ($l !== null && ! hash_equals((string) $l, (string) $licenseKey)) {
                return [false, 'Certificate is for a different license.'];
            }
        }
        if (isset($claims['status']) && in_array(strtolower((string) $claims['status']), ['expired', 'suspended', 'revoked', 'blocked', 'inactive', 'deactivated'], true)) {
            return [false, 'Certificate license is '.strtolower((string) $claims['status']).'.'];
        }

        return [true, $claims];
    }

    public static function revOf(array $claims): int
    {
        foreach (['rev', 'revocation_counter', 'counter', 'offline_revocation_counter'] as $k) {
            if (isset($claims[$k]) && is_numeric($claims[$k])) {
                return (int) $claims[$k];
            }
        }

        return 0;
    }

    public static function expOf(array $claims): ?int
    {
        foreach (['exp', 'offline_until', 'expires_at'] as $k) {
            if (isset($claims[$k]) && is_numeric($t = self::toTs($claims[$k])) && $t > 0) {
                return $t;
            }
        }

        return null;
    }

    /** Normalize a timestamp-ish claim (unix int or date string) to unix. */
    public static function toTs(mixed $v): int
    {
        if (is_numeric($v)) {
            $n = (int) $v;
            // Milliseconds → seconds (server clocks sometimes emit ms).
            if ($n > 410244480000) {
                $n = (int) round($n / 1000);
            }

            return $n;
        }
        $t = strtotime((string) $v);

        return $t === false ? 0 : $t;
    }

    /**
     * Normalize a keyring payload into [kid => raw-32-byte-key]. Accepts the
     * documented shapes plus JWK OKP/Ed25519 entries; unknown shapes yield [].
     */
    public static function normalizeKeyring(mixed $data): array
    {
        $out = [];
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        $take = function ($kid, $key) use (&$out) {
            $raw = self::keyBytes($key);
            if ($kid !== '' && $raw !== null) {
                $out[(string) $kid] = $raw;
            }
        };
        if (is_string($data)) {
            $take('default', $data);

            return $out;
        }
        if (! is_array($data)) {
            return $out;
        }
        $list = $data['keys'] ?? $data['keyring'] ?? $data['signing_keys'] ?? null;
        if (is_array($list) && array_is_list($list)) {
            foreach ($list as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $take((string) ($e['kid'] ?? $e['id'] ?? $e['key_id'] ?? ''), $e['key'] ?? $e['public_key'] ?? $e['x'] ?? null);
            }

            return $out;
        }
        if (isset($data['kty']) && isset($data['x'])) {
            $take((string) ($data['kid'] ?? 'default'), $data['x']);

            return $out;
        }
        if (isset($data['key']) || isset($data['public_key'])) {
            $take((string) ($data['kid'] ?? $data['key_id'] ?? 'default'), $data['key'] ?? $data['public_key']);

            return $out;
        }
        // Bare map kid => key.
        foreach ($data as $kid => $key) {
            if (is_string($key)) {
                $take($kid, $key);
            }
        }

        return $out;
    }

    /** base64url/base64/hex → raw 32-byte Ed25519 key, or null. */
    public static function keyBytes(mixed $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }
        $cands = [$key, rtrim($key, '=')];
        foreach ($cands as $c) {
            $raw = base64_decode(strtr($c, '-_', '+/'), true);
            if (is_string($raw) && strlen($raw) === 32) {
                return $raw;
            }
        }
        if (ctype_xdigit($key) && strlen($key) === 64) {
            return hex2bin($key);
        }

        return null;
    }

    protected static function b64dec(string $s, bool $raw = false): mixed
    {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode($s, true);
        if ($bin === false) {
            return $raw ? '' : null;
        }

        return $raw ? $bin : $bin;
    }
}

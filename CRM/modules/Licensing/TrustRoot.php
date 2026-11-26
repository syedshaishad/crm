<?php

namespace Modules\Licensing;

/**
 * The product's trust anchors and identity normalisers.
 *
 * Everything here comes from config/trust_root.php (plain PHP, no env()), so
 * the pins cannot be swapped by editing a .env file or a JSON file in storage.
 * The file fallback lets the standalone release builder use the same pins
 * without booting Laravel.
 */
final class TrustRoot
{
    private static ?array $fileConfig = null;

    public static function product(): string
    {
        return (string) self::cfg('trust_root.product', 'SSCCRM1000V1');
    }

    /** Lower-case host names, never full URLs. */
    public static function issuers(): array
    {
        $out = [];
        foreach ((array) self::cfg('trust_root.issuers', []) as $host) {
            $h = self::normalizeDomain((string) $host);
            if ($h !== '') {
                $out[] = $h;
            }
        }

        return array_values(array_unique($out));
    }

    /** kid => raw 32-byte Ed25519 public key. */
    public static function certKeys(): array
    {
        return self::keys((array) self::cfg('trust_root.certificates.keys', []));
    }

    /** kid => raw 32-byte Ed25519 public key. */
    public static function updateKeys(): array
    {
        return self::keys((array) self::cfg('trust_root.updates.keys', []));
    }

    public static function maxOfflineSeconds(): int
    {
        return max(86400, min(30 * 86400, (int) self::cfg('trust_root.certificates.max_offline_seconds', 7 * 86400)));
    }

    public static function requireBinding(): bool
    {
        return (bool) self::cfg('trust_root.certificates.require_binding', true);
    }

    /** Normalise a host (or URL) for binding: lowercase, no port, no www., no trailing dot. */
    public static function normalizeDomain(mixed $value): string
    {
        $h = strtolower(trim((string) $value));
        if ($h === '') {
            return '';
        }
        if (str_contains($h, '://')) {
            $h = (string) (parse_url($h, PHP_URL_HOST) ?? '');
        }
        $h = rtrim($h, '.');
        if (str_starts_with($h, '[')) {
            // [::1]:8000 → ::1
            $end = strpos($h, ']');
            if ($end !== false) {
                $h = substr($h, 1, $end - 1);
            }
        } else {
            $h = (string) preg_replace('/:\d+$/', '', $h);
        }
        if (str_starts_with($h, 'www.')) {
            $h = substr($h, 4);
        }

        return trim($h, "/ \t\n\r\0\x0B");
    }

    /**
     * A certificate's "dom" claim must match one of the runtime hosts.
     * Supports exact matches, "www." equivalence and "*.example.com".
     */
    public static function domainMatches(string $claim, array $actual): bool
    {
        $want = self::normalizeDomain($claim);
        if ($want === '' || $want === '*') {
            return true;
        }
        foreach ($actual as $candidate) {
            $got = self::normalizeDomain($candidate);
            if ($got === '') {
                continue;
            }
            if ($got === $want) {
                return true;
            }
            if (str_starts_with($want, '*.')) {
                $suffix = substr($want, 1); // ".example.com"
                if (str_ends_with($got, $suffix) && strlen($got) > strlen($suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Laravel config when available, otherwise the plain config file. */
    private static function cfg(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('config') && app()->bound('config')) {
                return config($key, $default);
            }
        } catch (\Throwable $e) {
        }
        if (self::$fileConfig === null) {
            $path = dirname(__DIR__, 2).'/config/trust_root.php';
            self::$fileConfig = is_file($path) ? (require $path) : [];
        }
        $path = str_starts_with($key, 'trust_root.') ? substr($key, strlen('trust_root.')) : $key;
        $value = self::$fileConfig;
        foreach (explode('.', $path) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private static function keys(array $configured): array
    {
        $out = [];
        foreach ($configured as $kid => $b64) {
            $raw = base64_decode((string) $b64, true);
            if ($kid !== '' && is_string($raw) && strlen($raw) === 32) {
                $out[(string) $kid] = $raw;
            }
        }

        return $out;
    }
}

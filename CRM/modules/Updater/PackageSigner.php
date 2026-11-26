<?php

namespace Modules\Updater;

use Modules\Licensing\TrustRoot;

/**
 * Seller-side signing and buyer-side verification of update packages.
 *
 * A package carries manifest.json (version, notes, per-file SHA-256) and
 * signature.json (Ed25519 signature over the exact manifest bytes). The
 * private key never leaves the seller; installs only know the public key
 * pinned in config/trust_root.php.
 *
 * The manifest doubles as an allowlist: every payload file must be listed and
 * match its hash, and nothing may be present that the manifest did not sign.
 */
final class PackageSigner
{
    public const MANIFEST = 'manifest.json';
    public const SIGNATURE = 'signature.json';
    public const SCHEMA = 'crm-update/1';

    /** @return string JSON bytes; the signature covers these bytes verbatim. */
    public static function encodeManifest(array $manifest): string
    {
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /** 32-byte seed (hex/base64) or 64-byte libsodium secret key → secret bytes. */
    public static function secretBytes(string $key): string
    {
        $key = trim($key);
        foreach ([$key, rtrim($key, '=')] as $candidate) {
            $raw = base64_decode(strtr($candidate, '-_', '+/'), true);
            if (is_string($raw) && strlen($raw) === 64) {
                return $raw;
            }
            if (is_string($raw) && strlen($raw) === 32) {
                return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($raw));
            }
        }
        if (ctype_xdigit($key)) {
            $raw = hex2bin($key);
            if (is_string($raw) && strlen($raw) === 64) {
                return $raw;
            }
            if (is_string($raw) && strlen($raw) === 32) {
                return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($raw));
            }
        }
        throw new \InvalidArgumentException('Signing key must be a 32-byte seed or 64-byte Ed25519 secret key (hex or base64).');
    }

    /** kid => raw 32-byte public key, derived from a seed for tooling/tests. */
    public static function publicFromSecret(string $key): string
    {
        return sodium_crypto_sign_publickey_from_secretkey(self::secretBytes($key));
    }

    public static function sign(string $manifestJson, string $privateKey, string $kid): string
    {
        $sig = sodium_crypto_sign_detached($manifestJson, self::secretBytes($privateKey));

        return self::encodeSignature([
            'alg' => 'ed25519',
            'kid' => $kid,
            'manifest_sha256' => hash('sha256', $manifestJson),
            'sig' => rtrim(strtr(base64_encode($sig), '+/', '-_'), '='),
        ]);
    }

    public static function encodeSignature(array $signature): string
    {
        return json_encode($signature, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * Verify signature bytes + manifest shape against pinned public keys.
     * Returns ['ok' => true, 'manifest' => [...]] or ['ok' => false, 'reason' => '…'].
     */
    public static function verify(string $manifestJson, string $signatureJson, array $pinnedKeys): array
    {
        $sigData = json_decode($signatureJson, true);
        if (! is_array($sigData) || ($sigData['alg'] ?? '') !== 'ed25519') {
            return self::fail('Package signature is missing or uses an unsupported algorithm.');
        }
        $kid = (string) ($sigData['kid'] ?? '');
        if ($kid === '' || ! isset($pinnedKeys[$kid])) {
            return self::fail('Package was signed by an unknown key — install a current release.');
        }
        $key = $pinnedKeys[$kid];
        if (! is_string($key) || strlen($key) !== 32 || ! preg_match('/^[A-Za-z0-9_-]+$/', (string) ($sigData['sig'] ?? ''))) {
            return self::fail('Package signature is malformed.');
        }
        $sig = base64_decode(strtr((string) $sigData['sig'], '-_', '+/'), true);
        if (! is_string($sig) || ! sodium_crypto_sign_verify_detached($sig, $manifestJson, $key)) {
            return self::fail('Package signature does not verify — this is not an official release.');
        }
        if (! hash_equals((string) ($sigData['manifest_sha256'] ?? ''), hash('sha256', $manifestJson))) {
            return self::fail('Package manifest digest does not match its signature.');
        }
        $manifest = json_decode($manifestJson, true);
        if (! is_array($manifest) || ($manifest['schema'] ?? '') !== self::SCHEMA) {
            return self::fail('Package manifest is missing or unsupported.');
        }
        if (($manifest['kid'] ?? '') !== $kid) {
            return self::fail('Package manifest and signature name different keys.');
        }
        if ((string) ($manifest['product'] ?? '') !== TrustRoot::product()) {
            return self::fail('Package is for a different product.');
        }
        if (! preg_match('/^\d+(?:\.\d+){1,3}(?:-[0-9A-Za-z.-]+)?$/', (string) ($manifest['version'] ?? ''))) {
            return self::fail('Package manifest has no usable version.');
        }
        if (! is_array($manifest['files'] ?? null)) {
            return self::fail('Package manifest has no file list.');
        }
        foreach ($manifest['files'] as $name => $hash) {
            if (! self::safeRel((string) $name) || ! preg_match('/^[a-f0-9]{64}$/i', (string) $hash)) {
                return self::fail('Package manifest contains an unsafe file entry.');
            }
        }

        return ['ok' => true, 'manifest' => $manifest, 'kid' => $kid];
    }

    /** SHA-256 of every file under the given package-root directories. */
    public static function treeHashes(string $root, array $dirs = ['files', 'migrations']): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $base = $root.'/'.$dir;
            if (! is_dir($base)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($it as $file) {
                if ($file->isLink()) {
                    throw new \RuntimeException('Package contains a symlink under '.$dir.'/.');
                }
                $rel = $dir.'/'.str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($base)), '/\\'));
                if (! self::safeRel($rel)) {
                    throw new \RuntimeException('Unsafe package path: '.$rel);
                }
                $out[$rel] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The extracted tree must match the signed manifest exactly: no missing
     * files, no changed files, and no smuggled extras.
     */
    public static function verifyTree(string $root, array $manifest): array
    {
        $want = $manifest['files'] ?? [];
        $actual = self::treeHashes($root);
        $missing = array_diff_key($want, $actual);
        $extra = array_diff_key($actual, $want);
        $changed = [];
        foreach ($want as $name => $hash) {
            if (isset($actual[$name]) && ! hash_equals(strtolower((string) $hash), strtolower((string) $actual[$name]))) {
                $changed[] = (string) $name;
            }
        }
        if ($extra !== []) {
            return self::fail('Package contains '.count($extra).' file(s) not listed in its signed manifest (first: '.array_key_first($extra).').');
        }
        if ($missing !== []) {
            return self::fail('Package is missing '.count($missing).' file(s) listed in its signed manifest (first: '.array_key_first($missing).').');
        }
        if ($changed !== []) {
            return self::fail('Package file contents differ from its signed manifest (first: '.$changed[0].').');
        }

        return ['ok' => true];
    }

    public static function safeRel(string $rel): bool
    {
        if ($rel === '' || str_starts_with($rel, '/') || str_starts_with($rel, '\\') || str_contains($rel, '\\')
            || preg_match('/^[A-Za-z]:/', $rel) || str_contains($rel, "\0")) {
            return false;
        }

        return ! in_array('..', explode('/', $rel), true);
    }

    /**
     * Sign a built zip in place. Used by build-package.php and artisan
     * update:sign. Root metadata files are replaced; payload is untouched.
     */
    public static function signZip(string $zipPath, string $privateKey, string $kid, array $meta = []): array
    {
        $tmp = sys_get_temp_dir().'/crm-sign-'.bin2hex(random_bytes(6));
        @mkdir($tmp, 0777, true);
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return self::fail('Cannot open update zip.');
        }
        $zip->extractTo($tmp);
        $zip->close();

        $root = is_dir($tmp.'/files') || is_dir($tmp.'/migrations') ? $tmp : $tmp;
        foreach (glob($tmp.'/*', GLOB_ONLYDIR) as $d) {
            if (is_dir($d.'/files') || is_dir($d.'/migrations')) {
                $root = $d;
                break;
            }
        }
        try {
            $hashes = self::treeHashes($root);
        } catch (\Throwable $e) {
            self::cleanup($tmp);

            return self::fail($e->getMessage());
        }
        if ($hashes === []) {
            self::cleanup($tmp);

            return self::fail('Update zip has no files/ or migrations/ payload.');
        }
        $manifest = $meta + [
            'schema' => self::SCHEMA,
            'product' => TrustRoot::product(),
            'kid' => $kid,
            'created_at' => gmdate('c'),
            'files' => $hashes,
        ];
        $manifestJson = self::encodeManifest($manifest);
        $signatureJson = self::sign($manifestJson, $privateKey, $kid);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            self::cleanup($tmp);

            return self::fail('Cannot rewrite update zip.');
        }
        foreach ([self::MANIFEST, self::SIGNATURE, 'signature.txt'] as $old) {
            $zip->deleteName($old);
        }
        $base = $root !== $tmp ? basename($root).'/' : '';
        $zip->addFromString($base.self::MANIFEST, $manifestJson);
        $zip->addFromString($base.self::SIGNATURE, $signatureJson);
        $zip->close();
        self::cleanup($tmp);

        return ['ok' => true, 'manifest' => $manifest, 'manifest_json' => $manifestJson, 'signature_json' => $signatureJson];
    }

    private static function fail(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason];
    }

    private static function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
        } catch (\Throwable $e) {
        }
        @rmdir($dir);
    }
}

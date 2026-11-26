<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\TrustRoot;
use Modules\Updater\PackageSigner;

/**
 * Seller-side: sign an update package with the Ed25519 private key.
 *
 *   php artisan update:sign --zip=update-v1.0.2.zip --private-key=deploy/keys/update-signing.key
 *
 * Adds manifest.json (version, notes, per-file SHA-256) and signature.json to
 * the zip root. Buyer installs verify both against the public key pinned in
 * config/trust_root.php; no code from the package is ever executed.
 */
class SignUpdate extends Command
{
    protected $signature = 'update:sign
        {--zip= : Update package to sign}
        {--private-key= : Path to the Ed25519 private key (seed), or the key itself}
        {--kid= : Key id from config/trust_root.php}
        {--licensee= : Optional customer/order watermark embedded in the manifest}';

    protected $description = 'Sign an update package with the release Ed25519 key.';

    public function handle(): int
    {
        $zip = (string) $this->option('zip');
        $rawKey = (string) $this->option('private-key');
        $kid = (string) $this->option('kid');
        if ($kid === '') {
            $kid = array_key_first(TrustRoot::updateKeys()) ?: '';
        }
        if (! is_file($zip)) {
            $this->error('Zip not found: '.$zip);

            return self::FAILURE;
        }
        if ($rawKey === '') {
            $this->error('Pass --private-key=<file> (never commit that file).');

            return self::FAILURE;
        }
        $key = is_file($rawKey) ? trim((string) file_get_contents($rawKey)) : $rawKey;
        if (! isset(TrustRoot::updateKeys()[$kid])) {
            $this->error('Unknown key id '.$kid.' — pin its public key in config/trust_root.php first.');

            return self::FAILURE;
        }
        try {
            $public = PackageSigner::publicFromSecret($key);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! hash_equals(TrustRoot::updateKeys()[$kid], $public)) {
            $this->error('Private key does not match the pinned public key '.$kid.'.');

            return self::FAILURE;
        }
        $meta = ['product' => TrustRoot::product()];
        if (($licensee = (string) $this->option('licensee')) !== '') {
            $meta['licensee'] = $licensee;
        }
        $result = PackageSigner::signZip($zip, $key, $kid, $meta);
        if (! $result['ok']) {
            $this->error($result['reason']);

            return self::FAILURE;
        }
        $this->info('Signed '.$zip.' (kid '.$kid.', '.count($result['manifest']['files']).' files).');

        return self::SUCCESS;
    }
}

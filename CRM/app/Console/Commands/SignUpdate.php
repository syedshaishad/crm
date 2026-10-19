<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Updater\UpdateManager;

/**
 * Seller-side: sign an update package so buyer installs with
 * UPDATE_RELEASE_KEY set refuse tampered/forged zips.
 *
 *   php artisan update:sign --zip=update-v1.0.2.zip --key=SECRET
 *
 * Writes signature.txt into the zip (the signature covers everything else).
 */
class SignUpdate extends Command
{
    protected $signature = 'update:sign
        {--zip= : Update package to sign}
        {--key= : Release key (HMAC secret)}';

    protected $description = 'Sign an update package for verified installs.';

    public function handle(UpdateManager $updater): int
    {
        $zipPath = (string) $this->option('zip');
        $key = (string) $this->option('key');
        if (! is_file($zipPath)) {
            $this->error('Zip not found: '.$zipPath);

            return self::FAILURE;
        }
        if (strlen($key) < 16) {
            $this->error('Key too short (min 16 chars).');

            return self::FAILURE;
        }
        $tmp = storage_path('app/updates_tmp/sign-'.uniqid());
        @mkdir($tmp, 0755, true);
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error('Cannot open zip.');

            return self::FAILURE;
        }
        $zip->extractTo($tmp);
        $zip->close();
        // Reuse the same root/signature logic the installer verifies.
        $ref = new \ReflectionMethod($updater, 'packageRoot');
        $ref->setAccessible(true);
        $root = $ref->invoke($updater, $tmp);
        $sig = $updater->packageSignature($root, $key);
        file_put_contents($tmp.'/signature.txt', $sig."\n");
        // If the tree lives in a wrapper folder, the signature belongs there.
        if ($root !== $tmp) {
            file_put_contents($root.'/signature.txt', $sig."\n");
            @unlink($tmp.'/signature.txt');
        }
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error('Cannot rewrite zip.');

            return self::FAILURE;
        }
        $zip->deleteName('signature.txt');
        $base = $root !== $tmp ? basename($root).'/' : '';
        $zip->addFromString($base.'signature.txt', $sig."\n");
        $zip->close();
        $this->cleanupDir($tmp);
        $this->info('Signed '.$zipPath.' ('.substr($sig, 0, 12).'…).');

        return self::SUCCESS;
    }

    protected function cleanupDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}

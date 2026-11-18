<?php

namespace Modules\Updater;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Licensing\LicenseManager;
use Modules\Licensing\LicensifyClient;
use Modules\Licensing\LicensifyException;

/**
 * Automatic updates through the Licensify server.
 *
 * The server decides what an installation may run: POST /updates/check answers
 * with the latest release, its notes and the requirements that gate it — PHP
 * and Laravel minimums, whether a backup must be acknowledged, and whether the
 * release sits outside the major series the license covers. Nothing here
 * decides availability; it relays the verdict and refuses to install anything
 * the server did not hand over with a matching checksum.
 *
 * The package itself is fetched through POST /downloads/request, which returns
 * a short-lived single-use URL plus a SHA-256. The bytes are streamed to a
 * .part file, verified, and only then renamed into the updates directory where
 * UpdateManager::apply() takes over (backup, extract, migrate).
 *
 * Docs: https://license.shaishad.com/docs/updates
 */
class ReleaseFeed
{
    public function __construct(private readonly LicenseManager $licenses) {}

    /** Automatic updates need both the customer key and the product API credentials. */
    public function configured(): bool
    {
        return $this->licenses->licenseKey() !== '' && $this->client()->configured();
    }

    /** Path of the cached verdict, so the page does not phone home on every load. */
    public function cachePath(): string
    {
        return storage_path('app/updates/.remote-check.json');
    }

    /** Last successful check, or null when this install never asked. */
    public function lastCheck(): ?array
    {
        try {
            $raw = @file_get_contents($this->cachePath());
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            $data = json_decode($raw, true);
            if (! is_array($data) || empty($data['ok']) || empty($data['checked_at'])) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ask the license server what this installation is entitled to run.
     * Returns ['ok' => true, ...verdict] or ['ok' => false, 'error' => '...'].
     */
    public function check(): array
    {
        if ($this->licenses->licenseKey() === '') {
            return $this->fail('This install has no license key, so the license server cannot decide which release it may run.');
        }
        if (! $this->client()->configured()) {
            return $this->fail('Automatic updates need the product API credentials (LICENSE_API_KEY / LICENSE_API_SECRET) in .env.');
        }

        try {
            $res = $this->client()->checkUpdate([
                'product_code' => (string) config('licensing.product_code'),
                'license_key' => $this->licenses->licenseKey(),
                'current_version' => VersionTracker::current(),
                'domain' => $this->licenses->domain(),
                'installation_id' => $this->licenses->installationId(),
            ]);
        } catch (LicensifyException $e) {
            return $this->fail($this->humanError($e));
        } catch (\Throwable $e) {
            return $this->fail('Update check failed: '.$e->getMessage());
        }

        if (empty($res['success'])) {
            $message = (string) ($res['message'] ?? $res['error']['message'] ?? 'The license server refused the update check.');
            $code = (string) ($res['error_code'] ?? $res['error']['code'] ?? '');
            $this->forgetCache();

            return $this->fail($message.($code !== '' ? ' ('.$code.')' : ''));
        }

        $verdict = $this->verdict($res);
        $this->writeCache($verdict);

        return $verdict;
    }

    /**
     * Download a release through the license server, verify its SHA-256 and
     * leave it in the updates directory ready for UpdateManager::apply().
     */
    public function download(string $version): array
    {
        if (! preg_match('/^\d+(?:\.\d+){1,3}(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            return $this->fail('That is not a valid release number.');
        }
        if (! $this->configured()) {
            return $this->fail('Automatic updates need the license key and product API credentials in .env.');
        }

        try {
            $grant = $this->client()->requestDownload([
                'product_code' => (string) config('licensing.product_code'),
                'license_key' => $this->licenses->licenseKey(),
                'version' => $version,
                'domain' => $this->licenses->domain(),
                'installation_id' => $this->licenses->installationId(),
            ]);
        } catch (LicensifyException $e) {
            return $this->fail($this->humanError($e));
        } catch (\Throwable $e) {
            return $this->fail('Download request failed: '.$e->getMessage());
        }

        if (empty($grant['success']) || empty($grant['url'])) {
            $message = (string) ($grant['message'] ?? $grant['error']['message'] ?? 'The license server did not grant a download.');
            $code = (string) ($grant['error_code'] ?? $grant['error']['code'] ?? '');

            return $this->fail($message.($code !== '' ? ' ('.$code.')' : ''));
        }

        // A signed URL is handed to us over TLS; refuse to follow it anywhere
        // insecure so a compromised answer cannot downgrade the transport.
        $url = (string) $grant['url'];
        if (! preg_match('#^https://#i', $url)) {
            return $this->fail('The license server returned a non-HTTPS download URL; refusing to fetch it.');
        }

        $expected = $this->expectedSha256($grant);
        if ($expected === null) {
            return $this->fail('The license server did not return a usable SHA-256 for this package, so the download was discarded.');
        }

        $dir = (string) config('version.package_dir');
        @mkdir($dir, 0755, true);
        if (! is_dir($dir) || ! is_writable($dir)) {
            return $this->fail('The updates directory is not writable: '.$dir);
        }

        $filename = $this->safeFilename((string) ($grant['filename'] ?? ''), $version);
        $part = $dir.'/'.$filename.'.part';
        @unlink($part);

        try {
            $request = Http::timeout(600)->connectTimeout(15);
            if (! (bool) config('licensing.verify_ssl', true)) {
                $request = $request->withoutVerifying();
            }
            $response = $request->sink($part)->get($url);
        } catch (\Throwable $e) {
            @unlink($part);

            return $this->fail('Download failed: '.$e->getMessage());
        }

        if ($response->status() >= 400) {
            @unlink($part);

            return $this->fail('The license server refused the download (HTTP '.$response->status().').');
        }
        if (! is_file($part) || filesize($part) === 0) {
            @unlink($part);

            return $this->fail('The downloaded package was empty.');
        }

        $actual = (string) hash_file('sha256', $part);
        if (! hash_equals($expected, $actual)) {
            $bytes = (int) @filesize($part);
            @unlink($part);
            // The full digests go to the log: two 64-character hashes in a toast
            // overflow the screen and hide the useful part of the message.
            Log::warning('Update package checksum mismatch; download discarded', [
                'version' => $version,
                'expected' => $expected,
                'actual' => $actual,
                'url' => $url,
                'bytes' => $bytes,
            ]);

            return $this->fail('Package checksum mismatch — the download was discarded (expected '.substr($expected, 0, 12).'…, got '.substr($actual, 0, 12).'…). The full hashes are in the application log.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($part) !== true) {
            @unlink($part);

            return $this->fail('The downloaded file is not a readable .zip archive.');
        }
        $zip->close();

        $final = $dir.'/'.$filename;
        @unlink($final);
        if (! @rename($part, $final)) {
            if (! @copy($part, $final)) {
                @unlink($part);

                return $this->fail('The package was verified but could not be moved into '.$dir.'.');
            }
            @unlink($part);
        }

        return [
            'ok' => true,
            'path' => $final,
            'file' => $filename,
            'version' => $version,
            'size' => (int) filesize($final),
            'checksum' => $actual,
        ];
    }

    /** Normalise the server's answer into what the page renders. */
    protected function verdict(array $res): array
    {
        $requirements = is_array($res['requirements'] ?? null) ? $res['requirements'] : [];
        $latest = (string) ($res['latest_version'] ?? '');
        $updateAvailable = ! empty($res['update_available']);
        $downloadAvailable = ! empty($res['download_available']);
        $newLicense = ! empty($requirements['new_license_required']);

        $phpMin = trim((string) ($requirements['minimum_php_version'] ?? ''));
        $laravelMin = trim((string) ($requirements['minimum_laravel_version'] ?? ''));
        $phpOk = $phpMin === '' || version_compare(PHP_VERSION, $phpMin, '>=');
        $laravelOk = $laravelMin === '' || version_compare(app()->version(), $laravelMin, '>=');

        $blockers = [];
        if ($updateAvailable) {
            if ($newLicense) {
                $blockers[] = 'This release is outside the major series your license covers, so a new license is required.';
            }
            if (! $downloadAvailable && ! $newLicense) {
                $blockers[] = 'The server is not offering a download for this release.';
            }
            if (! $phpOk) {
                $blockers[] = 'Requires PHP '.$phpMin.' or newer — this server runs '.PHP_VERSION.'.';
            }
            if (! $laravelOk) {
                $blockers[] = 'Requires Laravel '.$laravelMin.' or newer — this app runs '.app()->version().'.';
            }
        }

        $changelog = (string) ($res['changelog_url'] ?? '');

        return [
            'ok' => true,
            'checked_at' => time(),
            'update_available' => $updateAvailable,
            'current_version' => (string) ($res['current_version'] ?? VersionTracker::current()),
            'latest_version' => $latest,
            'release_type' => (string) ($res['release_type'] ?? 'stable'),
            'release_notes' => (string) ($res['release_notes'] ?? ''),
            'published_at' => $res['published_at'] ?? null,
            'changelog_url' => preg_match('#^https?://#i', $changelog) ? $changelog : null,
            'download_available' => $downloadAvailable,
            'requirements' => $requirements,
            'blockers' => $blockers,
            'installable' => $updateAvailable && $downloadAvailable && ! $newLicense && $phpOk && $laravelOk && $latest !== '',
            'backup_acknowledgement_required' => ! empty($requirements['requires_backup_acknowledgement']),
        ];
    }

    /** Accept "sha256:<hex>", a bare hex digest, or an algorithm field. */
    protected function expectedSha256(array $grant): ?string
    {
        $raw = trim((string) ($grant['checksum'] ?? ''));
        $algorithm = strtolower(trim((string) ($grant['checksum_algorithm'] ?? 'sha256')));
        if (str_contains($raw, ':')) {
            [$prefix, $rest] = explode(':', $raw, 2);
            $algorithm = strtolower(trim($prefix));
            $raw = trim($rest);
        }
        if ($algorithm !== 'sha256' || ! preg_match('/^[0-9a-f]{64}$/i', $raw)) {
            return null;
        }

        return strtolower($raw);
    }

    protected function safeFilename(string $name, string $version): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || ! str_ends_with(strtolower($name), '.zip')) {
            $name = 'update-v'.$version.'.zip';
        }
        $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '-', $name);

        return $name !== '' ? $name : 'update-v'.$version.'.zip';
    }

    protected function client(): LicensifyClient
    {
        return LicensifyClient::fromConfig();
    }

    protected function writeCache(array $verdict): void
    {
        try {
            @mkdir(dirname($this->cachePath()), 0755, true);
            @file_put_contents($this->cachePath(), json_encode($verdict, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
        }
    }

    protected function forgetCache(): void
    {
        try {
            @unlink($this->cachePath());
        } catch (\Throwable $e) {
        }
    }

    protected function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }

    protected function humanError(LicensifyException $e): string
    {
        $message = $e->getMessage();
        if ($e->code2 !== null && $e->code2 !== '') {
            $message .= ' ('.$e->code2.')';
        }
        if ($e->retryAfter !== null) {
            $message .= ' Try again in '.$e->retryAfter.' seconds.';
        }

        return $message;
    }
}

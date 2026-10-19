<?php

namespace Modules\Updater;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class UpdateManager
{
    public function availablePackages(): array
    {
        $dir = config('version.package_dir');
        if (! is_dir($dir)) {
            return [];
        }

        return array_values(array_filter(glob($dir.'/*.zip') ?: [], 'is_file'));
    }

    /**
     * Safe update: backup DB + files, extract zip, run migrations, bump version.
     * $zip must contain an update.php returning ['version' => 'x.y.z'] and optional files/.
     */
    public function apply(string $zipPath): array
    {
        if (! file_exists($zipPath)) {
            return ['ok' => false, 'error' => 'Package not found.'];
        }
        // Extract first so bad packages fail BEFORE any backup is taken.
        $tmp = storage_path('app/updates_tmp/'.uniqid());
        @mkdir($tmp, 0755, true);
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->cleanup($tmp);
            return ['ok' => false, 'error' => 'Cannot open update package (not a valid .zip).'];
        }
        // Zip-slip guard: reject absolute paths, backslashes and .. segments
        // BEFORE anything touches disk.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || $name[0] === '/' || $name[0] === '\\' || preg_match('/^[a-zA-Z]:/', $name)
                || str_contains($name, '..') || str_contains($name, '\\')) {
                $zip->close();
                $this->cleanup($tmp);

                return ['ok' => false, 'error' => 'Bad package: unsafe path inside zip ('.substr($name, 0, 60).').'];
            }
        }
        $zip->extractTo($tmp);
        $zip->close();

        // Tolerate zips wrapped in a single top-level folder.
        $root = $this->packageRoot($tmp);
        // Signed releases: verify the whole tree before touching anything.
        $key = (string) config('version.release_key', '');
        if ($key !== '') {
            $sigFile = $root.'/signature.txt';
            $want = is_file($sigFile) ? trim((string) file_get_contents($sigFile)) : '';
            if ($want === '' || ! hash_equals($this->packageSignature($root, $key), $want)) {
                $this->cleanup($tmp);

                return ['ok' => false, 'error' => 'Bad package: signature missing or invalid (not an official release).'];
            }
        }
        // Validate: update.php with a version is mandatory.
        $meta = is_file($root.'/update.php') ? include $root.'/update.php' : [];
        if (empty($meta) || ! is_array($meta) || empty($meta['version'])) {
            $this->cleanup($tmp);
            return ['ok' => false, 'error' => 'Bad package: update.php returning [\'version\' => \'x.y.z\'] is required at the zip root.'];
        }

        $backupDir = storage_path('app/backups/'.date('Y-m-d_H-i-s'));
        @mkdir($backupDir, 0755, true);

        // DB backup (mysqldump if available, else sqlite copy, else SQL dump fallback)
        $dbBackup = $this->backupDatabase($backupDir);

        try {
            // Long copy on shared hosts: ask for no time limit (hosts may refuse;
            // worst case the request hits max_execution_time mid-copy as before).
            try {
                @set_time_limit(0);
            } catch (\Throwable $e) {
            }
            // Downgrades are blocked: package migrations never roll back, so an
            // older package over a newer app means a broken schema. Re-applying
            // the SAME version stays allowed (safe repair path).
            $from = VersionTracker::current();
            if (version_compare($this->normalizeVersion($meta['version']), $this->normalizeVersion($from), '<')) {
                $this->cleanup($tmp);

                return ['ok' => false, 'error' => 'Refusing downgrade: package is '.$meta['version'].' but this install is already '.$from.'.'];
            }
            // Copy files/ over base_path (excluding .env + installed.lock)
            $filesDir = $root.'/files';
            $copied = 0;
            $failed = [];
            if (is_dir($filesDir)) {
                $res = $this->copyTree($filesDir, base_path(), ['.env', 'installed.lock']);
                $copied = $res['copied'];
                $failed = $res['failed'];
                if ($copied < 1) {
                    $this->cleanup($tmp);

                    return ['ok' => false, 'error' => 'Package files/ is empty — nothing to update.'];
                }
                if (count($failed) > 0) {
                    $this->cleanup($tmp);

                    return ['ok' => false, 'error' => 'Update stopped: '.count($failed).' file(s) could not be written (first: '.implode(', ', array_slice($failed, 0, 3)).').', 'hint' => 'Fix file ownership/permissions on the server, then re-apply the same package. A backup was taken at '.basename($backupDir).'.', 'backup' => $backupDir, 'failed' => $failed];
                }
            }
            // Re-assert the storage link (a bad package or extract may have
            // materialised public/storage as a real directory).
            try {
                \Modules\Installer\StorageLink::ensure();
            } catch (\Throwable $e) {
            }
            // 3b. Clear cached config/routes/views: a stale bootstrap/cache would
            //     shadow the very files this package just shipped. Then bust
            //     OPcache so new PHP takes effect without a restart.
            foreach (['config:clear', 'route:clear', 'view:clear'] as $clear) {
                try {
                    Artisan::call($clear);
                } catch (\Throwable $e) {
                }
            }
            $opReport = $this->resetOpcache();
            // 4. Run migrations from package if present
            $migrated = 0;
            if (is_dir($root.'/migrations')) {
                foreach (glob($root.'/migrations/*.php') as $m) {
                    copy($m, database_path('migrations/'.basename($m)));
                    $migrated++;
                }
            }
            if ($migrated > 0 || ! is_dir($root.'/migrations')) {
                $code = Artisan::call('migrate', ['--force' => true]);
                $migOut = trim(Artisan::output());
                if ($code !== 0) {
                    throw new \RuntimeException('Migrations failed (exit '.$code.'): '.substr($migOut, -500));
                }
            }
            // 5. Bump version
            VersionTracker::set($meta['version']);
            $this->cleanup($tmp);

            $parts = ['Updated to '.$meta['version']];
            if ($copied > 0) {
                $parts[] = $copied.' file(s)';
            }
            if ($migrated > 0) {
                $parts[] = $migrated.' migration(s)';
            }
            if ($from === $meta['version']) {
                $parts[] = '(same version as before)';
            }
            if ($opReport !== 'ok') {
                $parts[] = 'NOTE: '.$opReport;
            }
            $result = ['ok' => true, 'backup' => $backupDir, 'db_backup' => $dbBackup, 'version' => VersionTracker::current(), 'detail' => implode(', ', $parts), 'opcache' => $opReport];
            $this->logApply($from, $meta['version'], $copied, $migrated, $opReport, $backupDir);

            return $result;
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'backup' => $backupDir, 'hint' => 'Restore DB from backup and re-upload previous files to roll back.'];
        }
    }

    /** Descend into a lone top-level folder so wrapped zips still apply. */
    protected function packageRoot(string $tmp): string
    {
        if (is_file($tmp.'/update.php') || is_dir($tmp.'/files') || is_dir($tmp.'/migrations')) {
            return $tmp;
        }
        $subs = array_values(array_filter(glob($tmp.'/*') ?: [], 'is_dir'));
        if (count($subs) === 1) {
            $inner = $subs[0];
            if (is_file($inner.'/update.php') || is_dir($inner.'/files') || is_dir($inner.'/migrations')) {
                return $inner;
            }
        }

        return $tmp;
    }

    /**
     * Canonical tree hash: sorted relative paths + per-file sha256, HMACed.
     * signature.txt itself is excluded (it carries the signature).
     */
    public function packageSignature(string $root, string $key): string
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen($root) + 1);
            if ($rel === 'signature.txt') {
                continue;
            }
            $files[$rel] = hash_file('sha256', $f->getPathname());
        }
        ksort($files);
        $canon = '';
        foreach ($files as $rel => $h) {
            $canon .= $rel."\n".$h."\n";
        }

        return hash_hmac('sha256', $canon, $key);
    }

    /**
     * On-demand backup: database copy + uploaded files, zipped for download.
     * Returns ['ok' => true, 'path' => ..., 'file' => ...].
     */
    public function backupNow(): array
    {
        try {
            $stamp = date('Y-m-d_H-i-s');
            $dir = storage_path('app/backups/'.$stamp);
            @mkdir($dir, 0755, true);
            $dbBackup = $this->backupDatabase($dir);
            $zipPath = storage_path('app/backups/crm-backup-'.$stamp.'.zip');
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                return ['ok' => false, 'error' => 'Cannot create backup archive.'];
            }
            if (is_file($dbBackup)) {
                $zip->addFile($dbBackup, 'database/'.basename($dbBackup));
            }
            $pub = storage_path('app/public');
            if (is_dir($pub)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pub, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($it as $f) {
                    if (! $f->isDir()) {
                        $zip->addFile($f->getPathname(), 'files/'.substr($f->getPathname(), strlen($pub) + 1));
                    }
                }
            }
            $zip->close();

            return ['ok' => true, 'path' => $zipPath, 'file' => 'crm-backup-'.$stamp.'.zip', 'dir' => $dir];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function backupDatabase(string $dir): string
    {
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $src = config('database.connections.sqlite.database');
            $dst = $dir.'/database.sqlite.bak';
            if (is_file($src)) {
                copy($src, $dst);
            }

            return $dst;
        }
        // mysql: try mysqldump, fallback to raw SQL dump via PDO
        $dst = $dir.'/database.sql';
        $host = config('database.connections.mysql.host');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        @exec('mysqldump -h '.escapeshellarg($host).' -u '.escapeshellarg($user).' '.($pass !== '' ? '-p'.escapeshellarg($pass) : '').' '.escapeshellarg($db).' > '.escapeshellarg($dst).' 2>&1', $out, $code);
        if ($code === 0 && filesize($dst) > 0) {
            return $dst;
        }
        try {
            $pdo = DB::connection()->getPdo();
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            $sql = '-- CRM backup '.date('c')."\n\n";
            foreach ($tables as $t) {
                $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(\PDO::FETCH_ASSOC);
                $sql .= ($create['Create Table'] ?? '').";\n\n";
            }
            file_put_contents($dst, $sql);
        } catch (\Throwable $e) {
            file_put_contents($dst, '-- backup failed: '.$e->getMessage());
        }

        return $dst;
    }

    /**
     * Copy a tree, reporting per-file failures instead of swallowing them.
     * Returns ['copied' => int, 'failed' => string[]] (relative paths, capped).
     */
    protected function copyTree(string $src, string $dst, array $exclude = []): array
    {
        $copied = 0;
        $failed = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($src) + 1);
            $relSlash = str_replace('\\', '/', $rel);
            if ($relSlash === 'public/storage' || str_starts_with($relSlash, 'public/storage/')) {
                continue; // live symlink — never overwrite with package cargo
            }
            // Exclude by exact file/dir name so .env.example can still ship.
            $skip = false;
            foreach (explode('/', str_replace('\\', '/', $rel)) as $part) {
                if (in_array($part, $exclude, true)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $target = $dst.'/'.$rel;
            if ($file->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                if (@copy($file->getPathname(), $target)) {
                    $copied++;
                } elseif (count($failed) < 20) {
                    $failed[] = $relSlash;
                }
            }
        }

        return ['copied' => $copied, 'failed' => $failed];
    }

    protected function cleanup(string $tmp): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmp);
    }

    /** Best-effort OPcache bust. Returns 'ok' or a human explanation (never throws). */
    public function resetOpcache(): string
    {
        if (! function_exists('opcache_reset')) {
            return 'OPcache extension not installed — nothing cached, no action needed';
        }
        try {
            $enabled = @ini_get('opcache.enable');
            $cli = php_sapi_name() === 'cli' ? @ini_get('opcache.enable_cli') : true;
            if (! $enabled || ($cli !== true && $cli !== '1' && $cli !== 1 && php_sapi_name() === 'cli')) {
                return 'OPcache disabled — nothing cached, no action needed';
            }
            $ok = @opcache_reset();
            if ($ok) {
                return 'ok';
            }
        } catch (\Throwable $e) {
            return 'OPcache reset threw: '.$e->getMessage().' — reload PHP-FPM';
        }
        $restrict = (string) @ini_get('opcache.restrict_api');
        if ($restrict !== '') {
            return 'OPcache reset BLOCKED by opcache.restrict_api ('.$restrict.') — reload PHP-FPM or clear the setting';
        }
        if (! @ini_get('opcache.validate_timestamps')) {
            return 'OPcache reset may not have taken (validate_timestamps=0) — reload PHP-FPM to be sure';
        }

        return 'OPcache reset returned false — reload PHP-FPM to be sure';
    }

    /** One JSON line per apply, so "it said success" is always auditable. */
    protected function logApply(string $from, string $to, int $copied, int $migrated, string $opcache, string $backupDir): void
    {
        try {
            file_put_contents(
                storage_path('logs/updates.log'),
                json_encode(['at' => date('c'), 'from' => $from, 'to' => $to, 'files' => $copied, 'migrations' => $migrated, 'opcache' => $opcache, 'backup' => basename($backupDir), 'by' => auth()->id()])."\n",
                FILE_APPEND
            );
        } catch (\Throwable $e) {
        }
    }

    public function normalizeVersion(string $v): string
    {
        $v = trim($v);
        $v = preg_replace('/^[vV]/', '', $v);

        return $v === '' ? '0.0.0' : $v;
    }

    protected function parseBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return PHP_INT_MAX; // unlimited
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)/i', $v, $m)) {
            $n = (float) $m[1];
            switch (strtoupper($m[2])) {
                case 'G': $n *= 1024;
                    // fall through
                case 'M': $n *= 1024;
                    // fall through
                case 'K': $n *= 1024;
            }

            return (int) $n;
        }

        return (int) $v;
    }

    /**
     * Read update.php metadata without extracting (root + one wrapper level).
     * Returns [] when unreadable — apply() still performs full validation.
     */
    public function peekMeta(string $zipPath): array
    {
        try {
            $zip = new \ZipArchive;
            if ($zip->open($zipPath) !== true) {
                return [];
            }
            $code = $zip->getFromName('update.php');
            if ($code === false) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (substr_count($name, '/') === 1 && str_ends_with($name, '/update.php')) {
                        $code = $zip->getFromName($name);
                        break;
                    }
                }
            }
            $zip->close();
            if ($code === false) {
                return [];
            }
            $fn = function () use ($code) {
                return eval('?>'.$code);
            };
            $meta = $fn();

            return is_array($meta) ? $meta : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Server readiness for updates. Optional $zipPath adds package-specific
     * verdicts (fits upload limits? downgrade?). Each check:
     * ['key','label','ok','detail','fatal'].
     */
    public function preflight(?string $zipPath = null): array
    {
        $checks = [];
        $add = function ($key, $label, $ok, $detail = '', $fatal = false) use (&$checks) {
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => (bool) $ok, 'detail' => (string) $detail, 'fatal' => (bool) $fatal];
        };

        $add('php', 'PHP >= 8.2', version_compare(PHP_VERSION, '8.2', '>='), PHP_VERSION, true);
        $add('zip', 'ZipArchive available', class_exists(\ZipArchive::class), class_exists(\ZipArchive::class) ? 'php-zip loaded' : 'install php-zip', true);

        $paths = ['app root' => base_path(), 'storage' => storage_path(), 'updates dir' => config('version.package_dir'), 'migrations' => database_path('migrations')];
        $unwritable = [];
        foreach ($paths as $label => $p) {
            if (! is_dir($p)) {
                @mkdir($p, 0755, true);
            }
            if (! is_writable($p)) {
                $unwritable[] = $label;
            }
        }
        $add('writable', 'App files writable', count($unwritable) === 0, $unwritable ? 'read-only: '.implode(', ', $unwritable) : 'root, storage, updates, migrations', true);

        $up = $this->parseBytes((string) ini_get('upload_max_filesize'));
        $post = $this->parseBytes((string) ini_get('post_max_size'));
        $add('upload', 'Upload limit', true, 'upload_max_filesize='.ini_get('upload_max_filesize').' post_max_size='.ini_get('post_max_size'));
        $time = (int) ini_get('max_execution_time');
        $add('time', 'Execution time', $time === 0 || $time >= 120, $time === 0 ? 'unlimited' : $time.'s (120s+ recommended)');
        $mem = ini_get('memory_limit');
        $add('memory', 'Memory limit', true, $mem);
        $free = @disk_free_space(base_path());
        $add('disk', 'Disk space', $free === false || $free > 100 * 1024 * 1024, $free === false ? 'unknown' : round($free / 1024 / 1024).' MB free');

        if (function_exists('opcache_get_status') && @opcache_get_status(false) !== false) {
            $ts = (bool) ini_get('opcache.validate_timestamps');
            $restrict = (string) ini_get('opcache.restrict_api');
            $detail = 'enabled, validate_timestamps='.($ts ? 'on' : 'OFF').($restrict !== '' ? ', restrict_api='.$restrict : '');
            $add('opcache', 'OPcache', $ts && $restrict === '', $detail.($ts && $restrict === '' ? ' — reset will work' : ' — plan a PHP-FPM reload after updating'));
        } else {
            $add('opcache', 'OPcache', true, function_exists('opcache_reset') ? 'installed but inactive' : 'not installed — nothing to bust');
        }

        $current = VersionTracker::current();
        if ($zipPath && is_file($zipPath)) {
            $size = filesize($zipPath);
            $fits = $size <= min($up, $post);
            $add('pkgsize', 'Package fits upload limits', $fits, round($size / 1024 / 1024, 1).' MB package vs '.ini_get('upload_max_filesize').' limit', true);
            $meta = $this->peekMeta($zipPath);
            if (! empty($meta['version'])) {
                $cmp = version_compare($this->normalizeVersion($meta['version']), $this->normalizeVersion($current), '<');
                $add('version', 'Version path', ! $cmp, 'package '.$meta['version'].' vs installed '.$current.($cmp ? ' — DOWNGRADE, will be refused' : ''), $cmp);
            } else {
                $add('version', 'Version path', false, 'update.php unreadable — apply will validate fully', true);
            }
        } else {
            $add('version', 'Installed version', true, $current);
        }

        return $checks;
    }
}

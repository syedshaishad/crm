<?php

namespace Modules\Installer;

class RequirementsChecker
{
    public function check(): array
    {
        $minPhp = config('installer.min_php', '8.2.0');
        $phpOk = version_compare(PHP_VERSION, $minPhp, '>=');
        $extensions = [];
        foreach (config('installer.required_extensions', []) as $ext) {
            $loaded = extension_loaded($ext);
            // pdo driver note: pdo_mysql or pdo_sqlite acceptable
            $extensions[$ext] = $loaded;
        }
        $writable = [];
        foreach (config('installer.required_writable', []) as $path) {
            $full = base_path($path);
            if (! file_exists($full)) {
                @mkdir($full, 0755, true);
            }
            $writable[$path] = is_writable($full);
        }
        $functions = [
            'symlink capability' => function_exists('symlink'),
            'zip (updates)' => class_exists('ZipArchive'),
        ];
        $allOk = $phpOk && ! in_array(false, $extensions, true) && ! in_array(false, $writable, true);

        return [
            'php_version' => PHP_VERSION,
            'min_php' => $minPhp,
            'php_ok' => $phpOk,
            'extensions' => $extensions,
            'writable' => $writable,
            'functions' => $functions,
            'all_ok' => $allOk,
        ];
    }

    public function testDatabase(string $host, string $port, string $db, string $user, string $pass): array
    {
        try {
            $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');

            return ['ok' => true];
        } catch (\Throwable $e) {
            // Try connecting without dbname to give a clearer hint
            try {
                new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

                return ['ok' => false, 'error' => 'Connected to MySQL, but database "'.$db.'" is missing or unreadable for user "'.$user.'". Create it (or grant access) in your hosting panel, then retry.'];
            } catch (\Throwable $e2) {
                $hint = ' Check host/port, and that the user has ALL privileges on the database.';
                if (stripos($e2->getMessage(), 'Access denied') !== false) {
                    $hint = ' Credentials refused. Recreate the DB user with the exact password, grant ALL privileges on "'.$db.'", and retry.';
                }

                return ['ok' => false, 'error' => $e->getMessage().$hint];
            }
        }
    }

    /**
     * SQLite: no server, no credentials — just a file PHP must be able to
     * create and write. Opening it proves the pdo_sqlite driver is present;
     * the table round-trip proves the directory is actually writable (an
     * existing read-only file opens fine but fails on the first write).
     */
    public function testSqlite(string $path): array
    {
        try {
            if (! extension_loaded('pdo_sqlite')) {
                return ['ok' => false, 'error' => 'PHP has no pdo_sqlite extension, so a single-file database cannot be used here. Ask your host to enable it, or install MySQL instead.'];
            }
            $dir = dirname($path);
            if (! is_dir($dir)) {
                return ['ok' => false, 'error' => 'That folder does not exist: '.$dir];
            }
            if (is_dir($path)) {
                return ['ok' => false, 'error' => 'That path is a folder. Give the SQLite file a name, e.g. database/crm.sqlite.'];
            }
            if (file_exists($path) ? ! is_writable($path) : ! is_writable($dir)) {
                return ['ok' => false, 'error' => 'Not writable: '.$path.'. Fix the permissions on that folder, then retry.'];
            }
            $pdo = new \PDO('sqlite:'.$path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->exec('CREATE TABLE IF NOT EXISTS _crm_probe (id INTEGER PRIMARY KEY)');
            $pdo->exec('DROP TABLE _crm_probe');
            $pdo = null;

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'SQLite could not open that file: '.$e->getMessage()];
        }
    }
}

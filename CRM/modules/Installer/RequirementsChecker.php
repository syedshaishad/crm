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
}

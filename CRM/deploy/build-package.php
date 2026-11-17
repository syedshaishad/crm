<?php

/**
 * Build the CRM release, replacement and update packages.
 *
 *   php deploy/build-package.php                          # version from config/version.php
 *   php deploy/build-package.php --version=1.0.1          # explicit version
 *   php deploy/build-package.php --out="D:/releases"      # copy the zips somewhere
 *   php deploy/build-package.php --sign-key=SECRET        # sign update-*.zip (HMAC)
 *
 * Produces three files:
 *   crm-v<version>.zip             fresh-install package (vendor bundled)
 *   update-replace-v<version>.zip  byte-identical copy of the release zip
 *   update-v<version>.zip          update.php + migrations/ + files/ (in-app updater)
 *
 * Never ships install or licence identity: no .env, no database, no
 * storage/app/.installation_id / licence files, no logs, caches, symlinks,
 * uploads or executables (the licence server's validator refuses them).
 *
 * The script verifies its own output and exits non-zero on any problem, so a
 * broken artifact cannot be handed to a buyer silently.
 */

$root = dirname(__DIR__);
$options = parseOptions($argv);

$version = $options['version'] ?? defaultVersion($root);
$work = rtrim(sys_get_temp_dir(), '/\\').'/crm-build-'.date('Ymd-His').'-'.getmypid();
$stage = $work.'/crm-'.$version;
$out = $options['out'] ?? null;

echo "Building CRM v{$version}\n";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function parseOptions(array $argv): array
{
    $opts = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
            $opts[$m[1]] = $m[2] ?? true;
        }
    }

    return $opts;
}

/** Version fallback from config/version.php so the package cannot drift from the app. */
function defaultVersion(string $root): string
{
    $config = @file_get_contents($root.'/config/version.php') ?: '';
    if (preg_match("/'version'\s*=>\s*env\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $config, $m)) {
        return $m[1];
    }

    return '1.0.0';
}

function rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/** Copy a tree, pruning anything the package must not ship. */
function copyTree(string $src, string $dst, callable $skip, string $prefix = ''): void
{
    $dirIt = new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($dirIt, function ($current) use ($skip, $prefix, $src) {
        $rel = str_replace('\\', '/', ltrim(substr($current->getPathname(), strlen($src)), '/\\'));

        return ! $skip($prefix === '' ? $rel : $prefix.'/'.$rel, $current);
    });
    $it = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $rel = ltrim(substr($item->getPathname(), strlen($src)), '/\\');
        $target = $dst.'/'.str_replace('\\', '/', $rel);
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0777, true);
            }
        } else {
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function zipAddTree(ZipArchive $zip, string $src, string $prefix = ''): int
{
    $base = rtrim(str_replace('\\', '/', $src), '/');
    $added = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(substr(str_replace('\\', '/', $item->getPathname()), strlen($base)), '/');
        $name = $prefix === '' ? $rel : rtrim($prefix, '/').'/'.$rel;
        if ($item->isDir()) {
            $zip->addEmptyDir($name);
        } else {
            $zip->addFile($item->getPathname(), $name);
            $added++;
        }
    }

    return $added;
}

function zipHas(ZipArchive $z, string $name): bool
{
    if ($z->locateName($name) !== false || $z->locateName($name.'/') !== false) {
        return true;
    }
    for ($i = 0; $i < $z->numFiles; $i++) {
        $entry = $z->getNameIndex($i);
        if ($entry === $name || str_starts_with($entry, $name.'/')) {
            return true;
        }
    }

    return false;
}

// ---------------------------------------------------------------------------
// What may never ship
// ---------------------------------------------------------------------------

$skip = function (string $rel, SplFileInfo $item): bool {
    $base = basename($rel);
    if (in_array($base, ['.gitignore', '.DS_Store', 'Thumbs.db', 'build-package.php'], true)) {
        return true;
    }
    if (preg_match('/^\.env(\.|$)/', $base)) {
        return true;
    }
    if (preg_match('/\.(sqlite|sqlite3|log|phar|swp|tmp|bat|exe|sh|cmd|ps1|dll|so)$/i', $base)) {
        return true;
    }
    if (str_starts_with($rel, 'public/storage') || str_starts_with($rel, 'public/_preview')) {
        return true;
    }
    if ($rel === 'public/hot' || str_starts_with($rel, 'bootstrap/cache/')) {
        return true;
    }

    return false;
};

// ---------------------------------------------------------------------------
// Stage the tree
// ---------------------------------------------------------------------------

rrmdir($work);
mkdir($stage, 0777, true);

foreach (['.htaccess', 'artisan', 'CHANGELOG.md', 'composer.json', 'composer.lock', 'README-INSTALL.md', 'RESELLER-GUIDE.md'] as $file) {
    if (! is_file($root.'/'.$file)) {
        fwrite(STDERR, "Missing required root file: {$file}\n");
        exit(1);
    }
    copy($root.'/'.$file, $stage.'/'.$file);
}

foreach (['app', 'bootstrap', 'config', 'deploy', 'modules', 'public', 'resources', 'routes', 'vendor'] as $dir) {
    copyTree($root.'/'.$dir, $stage.'/'.$dir, $skip, $dir);
}

// Migrations only: never the live database or demo seeders.
copyTree($root.'/database/migrations', $stage.'/database/migrations', $skip, 'database/migrations');

// Storage scaffolding: empty dirs plus the example files, no uploads/logs/identity.
foreach (['app/private', 'app/public/branding', 'app/updates', 'framework'] as $dir) {
    if (! is_dir($stage.'/storage/'.$dir)) {
        mkdir($stage.'/storage/'.$dir, 0777, true);
    }
}
foreach (['storage/app/license.json.example', 'storage/app/updates/update.php.example'] as $example) {
    if (! is_file($root.'/'.$example)) {
        fwrite(STDERR, "Missing example file: {$example}\n");
        exit(1);
    }
    copy($root.'/'.$example, $stage.'/'.$example);
}

$files = 0;
$bytes = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $item) {
    $files++;
    $bytes += $item->getSize();
}
echo 'staged '.number_format($files).' files, '.number_format($bytes / 1048576, 1)." MB\n";

// ---------------------------------------------------------------------------
// Release zip (+ byte-identical replacement copy)
// ---------------------------------------------------------------------------

mkdir($work.'/out', 0777, true);
$crmZip = $work.'/out/crm-v'.$version.'.zip';
$zip = new ZipArchive();
if ($zip->open($crmZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create {$crmZip}\n");
    exit(1);
}
zipAddTree($zip, $stage);
$zip->close();
copy($crmZip, $work.'/out/update-replace-v'.$version.'.zip');

// ---------------------------------------------------------------------------
// Update zip: update.php + migrations/ + files/<tree>
// ---------------------------------------------------------------------------

$notes = $options['notes'] ?? 'Baseline release package. Apply via Settings -> Updates. A database + uploads backup is taken automatically before files/ is overlaid and migrations run.';
$updatePhp = $work.'/update.php';
file_put_contents($updatePhp, "<?php\n"
    ."// CRM {$version} update package. Upload via Settings -> Updates.\n"
    ."// A database + uploads backup is taken automatically before anything changes.\n"
    ."return [\n"
    ."    'version' => '{$version}',\n"
    ."    'requires' => '{$version}',\n"
    ."    'notes' => ".var_export($notes, true).",\n"
    ."];\n");

$updateZip = $work.'/out/update-v'.$version.'.zip';
$zip = new ZipArchive();
if ($zip->open($updateZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create {$updateZip}\n");
    exit(1);
}
$zip->addFile($updatePhp, 'update.php');
$zip->addEmptyDir('files');
zipAddTree($zip, $stage, 'files');
$zip->addEmptyDir('migrations');
zipAddTree($zip, $root.'/database/migrations', 'migrations');
$zip->close();

// ---------------------------------------------------------------------------
// Verify
// ---------------------------------------------------------------------------

$problems = [];

$mustHave = [
    'release' => ['artisan', 'composer.json', 'vendor/autoload.php', 'public/index.php', 'public/sw.js',
        'public/assets/css/app.css', 'resources/views/layouts/app.blade.php',
        'storage/app/license.json.example', 'storage/app/updates/update.php.example', 'database/migrations'],
    'update' => ['update.php', 'files/artisan', 'files/vendor/autoload.php',
        'files/public/assets/css/app.css', 'migrations'],
];
$mustNotHave = ['.env', 'database/database.sqlite', 'storage/app/.installation_id',
    'storage/app/.license_certificate.json', 'storage/app/.license_keyring.json',
    'storage/app/.license_secret', 'storage/app/license.key', 'storage/app/webpush_vapid.json',
    'storage/logs/laravel.log', 'bootstrap/cache/config.php', 'public/storage', 'node_modules'];

foreach (['release' => $crmZip, 'update' => $updateZip] as $label => $path) {
    $z = new ZipArchive();
    if ($z->open($path) !== true) {
        $problems[] = "{$label}: cannot open zip";
        continue;
    }
    $prefix = $label === 'update' ? 'files/' : '';
    foreach ($mustHave[$label] as $entry) {
        if (! zipHas($z, $entry)) {
            $problems[] = "{$label}: missing {$entry}";
        }
    }
    foreach ($mustNotHave as $entry) {
        if (zipHas($z, $prefix.$entry)) {
            $problems[] = "{$label}: forbidden entry {$prefix}{$entry}";
        }
    }
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $z->getNameIndex($i));
        if (preg_match('/\.(bat|exe|sh|cmd|ps1|dll|so|sqlite|log|phar)$/i', $name)) {
            $problems[] = "{$label}: executable/data file {$name}";
        }
    }
    echo $label.' zip: '.number_format($z->numFiles).' entries, '.number_format(filesize($path) / 1048576, 1)." MB\n";
    $z->close();
}

// The shipped views must all point at the same CSS build, and it must exist.
$versions = [];
$views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views', FilesystemIterator::SKIP_DOTS));
foreach ($views as $view) {
    if (! str_ends_with($view->getFilename(), '.blade.php')) {
        continue;
    }
    if (preg_match_all("/app\.css'\)\.'\?v=(\d+)/", (string) file_get_contents($view->getPathname()), $m)) {
        $versions = array_merge($versions, $m[1]);
    }
}
$versions = array_values(array_unique($versions));
if (count($versions) > 1) {
    $problems[] = 'views disagree on the app.css cache-buster: '.implode(', ', $versions);
}
if ($versions === []) {
    $problems[] = 'no app.css cache-buster found in the views';
}
echo 'css build: ?v='.($versions[0] ?? '?')."\n";

if (sha1_file($crmZip) !== sha1_file($work.'/out/update-replace-v'.$version.'.zip')) {
    $problems[] = 'update-replace zip is not byte-identical to the release zip';
}

if ($problems) {
    fwrite(STDERR, "\nFAILED:\n - ".implode("\n - ", $problems)."\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Sign (optional) and deliver
// ---------------------------------------------------------------------------

if (! empty($options['sign-key'])) {
    $key = (string) $options['sign-key'];
    $cwd = getcwd();
    chdir($root);
    exec(escapeshellarg(PHP_BINARY).' artisan update:sign --zip='.escapeshellarg($updateZip).' --key='.escapeshellarg($key).' 2>&1', $signOut, $signCode);
    chdir($cwd);
    if ($signCode !== 0) {
        fwrite(STDERR, "Signing failed: ".implode("\n", $signOut)."\n");
        exit(1);
    }
    echo 'signed: '.trim(implode(' ', $signOut))."\n";
}

$names = ['crm-v'.$version.'.zip', 'update-replace-v'.$version.'.zip', 'update-v'.$version.'.zip'];
foreach ($names as $name) {
    if ($out) {
        if (! is_dir($out)) {
            mkdir($out, 0777, true);
        }
        copy($work.'/out/'.$name, rtrim($out, '/\\').'/'.$name);
    }
    $path = $out ? rtrim($out, '/\\').'/'.$name : $work.'/out/'.$name;
    echo sprintf("%-28s %8.2f MB  sha1 %s\n", $name, filesize($path) / 1048576, sha1_file($path));
}

if (! $out) {
    echo "\nArtifacts are in {$work}/out (pass --out=<dir> to copy them somewhere).\n";
}

echo "\nDone. Reminder: an install with UPDATE_RELEASE_KEY set rejects an unsigned update zip.\n";
rrmdir($stage);

<?php

/**
 * Build the CRM release, replacement and update packages.
 *
 *   php deploy/build-package.php                          # readable dev build
 *   php deploy/build-package.php --version=1.0.1 --licensee="Acme / ORD-123"
 *   php deploy/build-package.php --out="D:/releases"      # copy the zips somewhere else
 *   php deploy/build-package.php --variant=encoded --encoded-dir=build/encoded
 *   php deploy/build-package.php --variant=encoded --encode-cmd="ioncube_encoder --into {out} {file}"
 *
 * The encoded variant swaps the gate-boundary files listed in
 * deploy/encoded-files.php for encoder output before zipping, and is written
 * to releases/<version>/<variant>/. It needs ionCube/SourceGuardian output
 * and the matching loader on buyer servers. See deploy/ENCODED-BUILD.md.
 *
 * Signing is MANDATORY: an unsigned update package is refused by every
 * install. The private key never ships (deploy/keys/*.key is gitignored);
 * installs verify with the public key pinned in config/trust_root.php.
 *
 * Artifacts are copied to releases/<version>/ by default (plus SHA256SUMS.txt),
 * so a build never lives only in the OS temp directory.
 *
 * Produces three files:
 *   crm-v<version>.zip             fresh-install package (vendor bundled)
 *   update-replace-v<version>.zip  byte-identical copy of the release zip
 *   update-v<version>.zip          manifest.json + signature.json + migrations/ + files/
 *
 * Never ships install or licence identity: no .env, no database, no
 * storage/app/.installation_id / licence files, no logs, caches, symlinks,
 * uploads or executables (the licence server's validator refuses them).
 *
 * The script verifies its own output and exits non-zero on any problem, so a
 * broken artifact cannot be handed to a buyer silently.
 */

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$options = parseOptions($argv);

$version = $options['version'] ?? defaultVersion($root);
$work = rtrim(sys_get_temp_dir(), '/\\').'/crm-build-'.date('Ymd-His').'-'.getmypid();
$stage = $work.'/crm-'.$version;

// Keep built packages in the project, one folder per version, so they survive
// temp cleanup. --out=<dir> still overrides this.
$variant = trim((string) ($options['variant'] ?? ''));
if ($variant !== '' && ! preg_match('/^[A-Za-z0-9._-]+$/', $variant)) {
    fwrite(STDERR, "Invalid --variant name.\n");
    exit(1);
}
$out = $options['out'] ?? null;
if ($out === null || $out === true || trim((string) $out) === '') {
    $out = $root.'/releases/'.$version.($variant !== '' ? '/'.$variant : '');
}
$out = rtrim((string) $out, '/\\');

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

/** The canonical gate-boundary file list for an encoded build. */
function encodedFileList(string $root): array
{
    $path = $root.'/deploy/encoded-files.php';
    $list = is_file($path) ? (require $path) : [];
    if (! is_array($list) || $list === []) {
        fwrite(STDERR, "deploy/encoded-files.php is missing or empty.\n");
        exit(1);
    }

    return array_values(array_map('strval', $list));
}

/**
 * Overlay encoder output onto the staged tree.
 *
 * --encoded-dir=<dir> : encoder already ran; mirror the relative paths there.
 * --encode-cmd=<cmd>  : template with {file} (input), {out} (output), {root}.
 * Both produce a second, signed build; the plaintext build is unchanged.
 */
function applyEncodedFiles(string $stage, string $root, string $work, array $options): int
{
    $encodedDir = $options['encoded-dir'] ?? null;
    $command = $options['encode-cmd'] ?? null;
    if ($encodedDir === null && $command === null) {
        return 0;
    }
    if ($encodedDir !== null && $command !== null) {
        fwrite(STDERR, "Use either --encoded-dir or --encode-cmd, not both.\n");
        exit(1);
    }
    $encodedDir = is_string($encodedDir) && $encodedDir !== '' ? $encodedDir : null;
    $command = is_string($command) && $command !== '' ? $command : null;
    if ($encodedDir !== null) {
        $encodedDir = str_starts_with($encodedDir, '/') || preg_match('/^[A-Za-z]:/', $encodedDir)
            ? rtrim(str_replace('\\', '/', $encodedDir), '/')
            : rtrim(getcwd().'/'.str_replace('\\', '/', $encodedDir), '/');
        if (! is_dir($encodedDir)) {
            fwrite(STDERR, "Encoded directory not found: {$encodedDir}\n");
            exit(1);
        }
    }
    if ($command !== null && (! str_contains($command, '{file}') || ! str_contains($command, '{out}'))) {
        fwrite(STDERR, "--encode-cmd must contain {file} and {out} placeholders.\n");
        exit(1);
    }

    $destRoot = $work.'/encoded';
    $count = 0;
    foreach (encodedFileList($root) as $rel) {
        $source = $stage.'/'.$rel;
        if (! is_file($source)) {
            fwrite(STDERR, "Encoded build: gate file missing from stage: {$rel}\n");
            exit(1);
        }
        if ($encodedDir !== null) {
            $candidate = $encodedDir.'/'.$rel;
        } else {
            $candidate = $destRoot.'/'.$rel;
            @mkdir(dirname($candidate), 0777, true);
            $cmd = str_replace(
                ['{file}', '{out}', '{root}'],
                [escapeshellarg($source), escapeshellarg($candidate), escapeshellarg($root)],
                (string) $command
            );
            $lines = [];
            exec($cmd, $lines, $code);
            if ($code !== 0) {
                fwrite(STDERR, "Encoder failed for {$rel}: ".implode(' ', $lines)."\n");
                exit(1);
            }
        }
        if (! is_file($candidate) || filesize($candidate) === 0) {
            fwrite(STDERR, "Encoded build: no encoder output for {$rel} (looked in {$candidate}).\n");
            exit(1);
        }
        if (hash_file('sha256', $candidate) === hash_file('sha256', $source)) {
            fwrite(STDERR, "Encoded build: {$rel} is identical to the readable source — the encoder did not run.\n");
            exit(1);
        }
        copy($candidate, $source);
        $count++;
    }
    echo 'encoded gate files: '.$count.' of '.count(encodedFileList($root))."\n";

    return $count;
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
    // Never ship the private release key (or any private key material).
    if (str_starts_with($rel, 'deploy/keys/') || preg_match('/\.key$/i', $base)) {
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

// Per-order watermark. It is code, not env, so it survives updates and can
// identify a leaked build without exposing any credential.
$licensee = trim((string) ($options['licensee'] ?? ''));
file_put_contents($stage.'/config/licensee.php', "<?php\n\n// Written by deploy/build-package.php. Do not edit by hand.\nreturn [\n"
    ."    'customer' => ".var_export($licensee, true).",\n"
    ."    'version' => ".var_export($version, true).",\n"
    ."];\n");

// Optional protected variant: swap the gate boundary for encoder output
// BEFORE either zip is built, so fresh installs and updates stay consistent.
$encodedCount = applyEncodedFiles($stage, $root, $work, $options);

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
// Update zip: signed manifest + migrations/ + files/<tree>
// ---------------------------------------------------------------------------

$notes = $options['notes'] ?? 'Baseline release package. Apply via Settings -> Updates. A database + uploads backup is taken automatically before files/ is overlaid and migrations run.';
$updateZip = $work.'/out/update-v'.$version.'.zip';
$zip = new ZipArchive();
if ($zip->open($updateZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create {$updateZip}\n");
    exit(1);
}
$zip->addEmptyDir('files');
zipAddTree($zip, $stage, 'files');
$zip->addEmptyDir('migrations');
zipAddTree($zip, $root.'/database/migrations', 'migrations');
$zip->close();

// Mandatory signing. A release without a valid signature cannot be installed.
$kid = (string) ($options['kid'] ?? array_key_first(\Modules\Licensing\TrustRoot::updateKeys()));
$pinned = \Modules\Licensing\TrustRoot::updateKeys();
if (! isset($pinned[$kid])) {
    fwrite(STDERR, "Unknown signing key id {$kid}; pin its public key in config/trust_root.php.\n");
    exit(1);
}
$keyFile = (string) ($options['sign-key-file'] ?? 'deploy/keys/update-signing.key');
$rawKey = (string) ($options['sign-key'] ?? '');
if ($rawKey === '' && is_file($root.'/'.$keyFile)) {
    $rawKey = trim((string) file_get_contents($root.'/'.$keyFile));
}
if ($rawKey === '') {
    fwrite(STDERR, "No signing key. Pass --sign-key=<hex seed> or --sign-key-file=<path> (default deploy/keys/update-signing.key).\n");
    exit(1);
}
try {
    $derived = \Modules\Updater\PackageSigner::publicFromSecret($rawKey);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Bad signing key: '.$e->getMessage()."\n");
    exit(1);
}
if (! hash_equals($pinned[$kid], $derived)) {
    fwrite(STDERR, "The signing key does not match the pinned public key {$kid}.\n");
    exit(1);
}
$signed = \Modules\Updater\PackageSigner::signZip($updateZip, $rawKey, $kid, [
    'version' => $version,
    'requires' => $version,
    'notes' => $notes,
    'licensee' => $licensee,
]);
if (! $signed['ok']) {
    fwrite(STDERR, 'Signing failed: '.$signed['reason']."\n");
    exit(1);
}
echo 'signed update: '.$kid.' ('.number_format(count($signed['manifest']['files']))." files)\n";

// ---------------------------------------------------------------------------
// Verify
// ---------------------------------------------------------------------------

$problems = [];

$mustHave = [
    'release' => ['artisan', 'composer.json', 'vendor/autoload.php', 'public/index.php', 'public/sw.js',
        'public/assets/css/app.css', 'resources/views/layouts/app.blade.php',
        'storage/app/license.json.example', 'storage/app/updates/update.php.example', 'database/migrations'],
    'update' => ['manifest.json', 'signature.json', 'files/artisan', 'files/vendor/autoload.php',
        'files/public/assets/css/app.css', 'files/config/licensee.php', 'migrations'],
];
$mustNotHave = ['.env', 'database/database.sqlite', 'storage/app/.installation_id',
    'storage/app/.license_certificate.json', 'storage/app/.license_keyring.json',
    'storage/app/.license_secret', 'storage/app/license.key', 'storage/app/webpush_vapid.json',
    'storage/logs/laravel.log', 'bootstrap/cache/config.php', 'public/storage', 'node_modules',
    'deploy/keys/update-signing.key'];

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

if ($encodedCount > 0) {
    foreach (['release' => [$crmZip, ''], 'update' => [$updateZip, 'files/']] as $label => [$path, $prefix]) {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            $problems[] = "{$label}: cannot reopen zip for encoded-file check";
            continue;
        }
        foreach (encodedFileList($root) as $rel) {
            $shipped = $z->getFromName($prefix.$rel);
            if ($shipped === false || hash('sha256', (string) $shipped) === hash_file('sha256', $root.'/'.$rel)) {
                $problems[] = "{$label}: gate file {$rel} is readable source in the encoded build";
            }
        }
        $z->close();
    }
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
// Deliver
// ---------------------------------------------------------------------------

$names = ['crm-v'.$version.'.zip', 'update-replace-v'.$version.'.zip', 'update-v'.$version.'.zip'];
if (! is_dir($out)) {
    mkdir($out, 0777, true);
}
$sums = [];
foreach ($names as $name) {
    $path = $out.'/'.$name;
    copy($work.'/out/'.$name, $path);
    $sums[] = hash_file('sha256', $path).'  '.$name;
    echo sprintf("%-28s %8.2f MB  sha256 %s\n", $name, filesize($path) / 1048576, hash_file('sha256', $path));
}
file_put_contents($out.'/SHA256SUMS.txt', implode("\n", $sums)."\n");

echo "\nArtifacts are in {$out}".($variant !== '' ? " (variant: {$variant})" : '')."\n";

echo "\nDone. Every install verifies the Ed25519 manifest; unsigned packages are refused.\n";
rrmdir($stage);

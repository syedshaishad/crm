<?php

namespace Modules\Installer;

/**
 * Guarantees public/storage is a working symlink to storage/app/public.
 *
 * Failure mode it prevents: deployment zips/extracts sometimes materialise
 * public/storage as a REAL directory (e.g. a resolved Windows junction).
 * The directory then shadows uploads AND makes `storage:link` refuse with
 * "already exists" — every logo/receipt/document image breaks site-wide.
 */
class StorageLink
{
    /**
     * @return null|string null when healthy, otherwise a human-readable problem.
     */
    public static function ensure(): ?string
    {
        $link = public_path('storage');
        $target = storage_path('app/public');
        try {
            if (self::isHealthyLink($link)) {
                return null;
            }
            if (! is_dir($target)) {
                @mkdir($target, 0755, true);
            }
            if (file_exists($link) || is_link($link)) {
                // Real directory in the way: salvage any strays into the true
                // target first, then drop the impostor node (never traverse
                // into junctions — rmdir removes the link node only).
                self::salvage($link, $target);
                self::removeNode($link);
            }
            if (function_exists('symlink')) {
                @symlink($target, $link);
            } else {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            }
            if (! self::isHealthyLink($link)) {
                return 'Could not create the public/storage link — check folder permissions.';
            }
        } catch (\Throwable $e) {
            return 'Storage link problem: '.$e->getMessage();
        }

        return null;
    }

    protected static function isHealthyLink(string $link): bool
    {
        if (is_link($link)) {
            return is_dir($link);
        }
        // Windows junction: realpath escapes the node itself.
        $real = realpath($link);
        if ($real !== false && rtrim($real, '/\\') !== rtrim($link, '/\\')) {
            return true;
        }

        return false;
    }

    protected static function salvage(string $link, string $target): void
    {
        $real = realpath($link);
        if ($real === false || rtrim($real, '/\\') === rtrim($link, '/\\')) {
            // Genuine directory (not a link): merge strays into the target.
            foreach (@scandir($link) ?: [] as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                @rename($link.'/'.$child, $target.'/'.$child);
            }
        }
    }

    protected static function removeNode(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);

            return;
        }
        $real = realpath($path);
        if ($real !== false && rtrim($real, '/\\') !== rtrim($path, '/\\')) {
            @rmdir($path); // junction/link node only — target untouched

            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($path);
    }
}

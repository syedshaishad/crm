<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Settings\SettingStore;
use Modules\Updater\UpdateManager;

class RunBackups extends Command
{
    protected $signature = 'backups:run';

    protected $description = 'Nightly full backup (database + files) with retention pruning.';

    public function handle(UpdateManager $updater): int
    {
        $result = $updater->backupNow();
        if (! ($result['ok'] ?? false)) {
            $this->error('Backup failed: '.($result['error'] ?? 'unknown'));

            return self::FAILURE;
        }
        $this->info('Backup created: '.$result['file']);
        $this->mailBackup($result);
        $keep = max(1, min(30, (int) app(SettingStore::class)->get('backups.retain_count', 7)));
        $keep = max(1, min(30, (int) app(SettingStore::class)->get('backups.retain_count', 7)));
        $dir = storage_path('app/backups');
        $zips = glob($dir.'/crm-backup-*.zip') ?: [];
        usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($zips, $keep) as $old) {
            @unlink($old);
            $stamp = basename($old, '.zip');
            $stamp = preg_replace('/^crm-backup-/', '', $stamp);
            $this->rmdir(storage_path('app/backups/'.$stamp));
            $this->info('Pruned '.basename($old));
        }

        return self::SUCCESS;
    }

    /** Email the zip to the owner (skipped when too big for inboxes). Mail failure never fails the backup. */
    protected function mailBackup(array $result): void
    {
        $say = function (string $msg) {
            try {
                $this->info($msg);
            } catch (\Throwable $e) {
            }
        };
        $warn = function (string $msg) use ($say) {
            try {
                $this->warn($msg);
            } catch (\Throwable $e) {
                $say($msg);
            }
        };
        try {
            $store = app(SettingStore::class);
            if ($store->get('backups.email_enabled', '0') !== '1') {
                return;
            }
            $to = trim((string) $store->get('backups.email_to', ''));
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $warn('Backup email skipped: no valid address (Settings → Updates).');

                return;
            }
            $path = $result['path'] ?? '';
            if (! is_file($path)) {
                return;
            }
            if (filesize($path) > 20 * 1024 * 1024) {
                $warn('Backup email skipped: zip over 20 MB — download it from Settings → Updates.');

                return;
            }
            $subject = 'Backup '.($result['file'] ?? date('Y-m-d'));
            $body = 'Nightly backup attached ('.($result['file'] ?? 'backup.zip').'). Store it somewhere safe — it holds the full database plus uploaded files.';
            Mail::send('emails.template', ['body' => $body], function ($m) use ($to, $subject, $path) {
                $m->to($to)->subject($subject)->attach($path);
            });
            $say('Backup emailed to '.$to);
        } catch (\Throwable $e) {
            $warn('Backup email failed: '.$e->getMessage());
        }
    }

    protected function rmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}

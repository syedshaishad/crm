<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\LicenseManager;

/** Live license verdict: cached certificate, grace window, or server check. */
class CheckLicense extends Command
{
    protected $signature = 'license:check {--refresh : Force a live re-validation right now}';

    protected $description = 'Show license status (use --refresh to re-validate with the server).';

    public function handle(LicenseManager $lm): int
    {
        if ($this->option('refresh')) {
            [$ok, $msg] = $lm->refresh();
            $ok ? $this->info($msg) : $this->error($msg);
            if (! $ok) {
                return self::FAILURE;
            }
        }
        $s = $lm->status();
        $this->line('Server: '.config('licensing.server_url').' · product '.config('licensing.product_code'));
        $this->line('Installation: '.$lm->installationId().' · domain '.$lm->domain());
        $this->line('Active: '.($s['active'] ? 'yes' : 'NO'.(isset($s['reason']) ? ' — '.$s['reason'] : '')));
        $this->line('Plan: '.($s['license_type'] ?? $s['plan'] ?? '—').' ('.($s['status'] ?? 'unknown').')');
        $this->line('Expires: '.($s['expires_at'] ?? '—').' · grace until: '.($s['grace_until'] ?? '—').(isset($s['cached']) && $s['cached'] ? ' (cached certificate)' : ''));
        if (! empty($s['request_id'])) {
            $this->line('Server ref: '.$s['request_id']);
        }

        return $s['active'] ? self::SUCCESS : self::FAILURE;
    }
}

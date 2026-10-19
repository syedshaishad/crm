<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\LicenseManager;

/** Activate a reseller-issued license key from the terminal. */
class ActivateLicense extends Command
{
    protected $signature = 'license:activate {key : The license key from the reseller}';

    protected $description = 'Activate a license key against the license server.';

    public function handle(LicenseManager $lm): int
    {
        [$ok, $msg] = $lm->activate((string) $this->argument('key'));
        $ok ? $this->info($msg) : $this->error($msg);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

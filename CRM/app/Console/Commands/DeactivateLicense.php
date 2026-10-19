<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\LicenseManager;

/** Release this installation's activation slot (frees it for another server). */
class DeactivateLicense extends Command
{
    protected $signature = 'license:deactivate {--reason= : Why the slot is released}';

    protected $description = 'Release this installation activation slot on the license server.';

    public function handle(LicenseManager $lm): int
    {
        if (! $this->confirm('Release this activation slot? The app locks until a key is activated again.', false)) {
            return self::SUCCESS;
        }
        [$ok, $msg] = $lm->deactivate((string) $this->option('reason'));
        $ok ? $this->info($msg) : $this->error($msg);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

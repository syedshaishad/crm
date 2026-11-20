<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Renewals\RenewalService;

class RenewalsSync extends Command
{
    protected $signature = 'renewals:sync {--draft-days=45 : Auto-draft proposals this many days ahead of renewal}';

    protected $description = 'Refresh the renewal pipeline and draft proposals for warm renewals.';

    public function handle(RenewalService $service): int
    {
        $counts = $service->sync();
        $made = $service->autoDraft((int) $this->option('draft-days'));
        $this->info(sprintf('Renewals synced: %d AMC, %d expiry, %d recurring. %d draft proposal(s) created.',
            $counts['amc'], $counts['expiry'], $counts['recurring'], $made));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Proposals\Proposal;

class ProposalsMarkExpired extends Command
{
    protected $signature = 'proposals:mark-expired';

    protected $description = 'Mark sent proposals past their valid-until date as expired.';

    public function handle(): int
    {
        $ids = Proposal::where('status', 'sent')->whereNotNull('valid_until')
            ->where('valid_until', '<', now()->toDateString())->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Marked 0 proposal(s) expired.');

            return self::SUCCESS;
        }
        Proposal::whereIn('id', $ids)->update(['status' => 'expired']);
        $this->info("Marked {$ids->count()} proposal(s) expired.");

        return self::SUCCESS;
    }
}

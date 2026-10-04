<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use RCI\MemberEngagement\Services\DataCollectionService;
use Carbon\Carbon;

class SyncActivitiesCommand extends Command
{
    protected $signature = 'member-engagement:sync {--days=90 : Days back to sync}';

    protected $description = 'Sync member activities from SeAT data sources';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = Carbon::now()->subDays($days);

        $this->info("Syncing activities from the last {$days} days...");

        $service = app(DataCollectionService::class);
        $results = $service->collectAll($since);

        $this->info('Sync Complete!');
        $this->line('Results:');
        $this->line("  Mining: {$results['mining']} records");
        $this->line("  Kills: {$results['kills']} records");
        $this->line("  Losses: {$results['losses']} records");
        $this->line("  PvE Bounty/Tax: {$results['pve_bounty_tax']} records");
        $this->line("  Industry Tax: {$results['industry_tax']} records");
        $this->line("  Total: " . array_sum($results) . " records");

        return self::SUCCESS;
    }
}

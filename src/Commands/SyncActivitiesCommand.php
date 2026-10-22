<?php

namespace RCI\MemberEngagement\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Services\DataCollectionService;

class SyncActivitiesCommand extends Command
{
    /**
     * Left without a default on purpose: with no --days, the window sizes
     * itself to cover everything missed since the last successful run.
     */
    protected $signature = 'member-engagement:sync {--days= : Days back to sync (default: enough to cover the gap since the last successful run)}';

    protected $description = 'Sync member activities from SeAT data sources';

    /** Normal cadence when nothing has been missed. */
    private const DEFAULT_DAYS = 2;

    /** The collectors never look back further than this anyway. */
    private const MAX_DAYS = 90;

    public function handle(): int
    {
        $setting = MemberEngagementSetting::current();
        $days = $this->resolveDays($setting->last_sync_at);
        $since = Carbon::now()->subDays($days);

        $this->info("Syncing activities from the last {$days} days...");

        $service = app(DataCollectionService::class);
        $results = $service->collectAll($since);

        $setting->update(['last_sync_at' => Carbon::now()]);

        $this->info('Sync Complete!');
        $this->line('Results:');
        $this->line("  Mining: {$results['mining']} records");
        $this->line("  Kills: {$results['kills']} records");
        $this->line("  Losses: {$results['losses']} records");
        $this->line("  PvE Bounty/Tax: {$results['pve_bounty_tax']} records");
        $this->line("  Industry Tax: {$results['industry_tax']} records");
        $this->line('  Total: ' . array_sum($results) . ' records');

        return self::SUCCESS;
    }

    private function resolveDays(?Carbon $lastRun): int
    {
        if ($this->option('days') !== null) {
            return max(1, (int) $this->option('days'));
        }

        if (!$lastRun) {
            return self::DEFAULT_DAYS;
        }

        $missedDays = $lastRun->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay());

        if ($missedDays <= 1) {
            return self::DEFAULT_DAYS;
        }

        $days = min($missedDays + 1, self::MAX_DAYS);
        $this->warn("Last successful sync was {$missedDays} day(s) ago - catching up over {$days} days.");

        return $days;
    }
}

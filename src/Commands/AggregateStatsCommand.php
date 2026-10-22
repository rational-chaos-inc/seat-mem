<?php

namespace RCI\MemberEngagement\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Services\AggregationService;

class AggregateStatsCommand extends Command
{
    /**
     * Left without a default on purpose: with no --days, the window sizes
     * itself to cover everything missed since the last successful run.
     */
    protected $signature = 'member-engagement:aggregate {--days= : Days back to aggregate (default: enough to cover the gap since the last successful run)}';

    protected $description = 'Aggregate daily member statistics';

    /** Normal cadence when nothing has been missed. */
    private const DEFAULT_DAYS = 3;

    private const MAX_DAYS = 90;

    public function handle(AggregationService $service): int
    {
        $setting = MemberEngagementSetting::current();
        $days = $this->resolveDays($setting->last_aggregate_at);

        $this->info("Aggregating daily stats for the last {$days} days...");

        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $count = $service->aggregateDay($date);
            $total += $count;
            $this->line("  {$date->toDateString()}: {$count} member records");
        }

        $setting->update(['last_aggregate_at' => Carbon::now()]);

        $this->info("Aggregation complete! Total: {$total} records");

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
        $this->warn("Last successful aggregate was {$missedDays} day(s) ago - catching up over {$days} days.");

        return $days;
    }
}

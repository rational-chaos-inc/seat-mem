<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use RCI\MemberEngagement\Services\AggregationService;

class AggregateStatsCommand extends Command
{
    protected $signature = 'member-engagement:aggregate {--days=7 : Days back to aggregate}';

    protected $description = 'Aggregate daily member statistics';

    public function handle(AggregationService $service): int
    {
        $days = (int) $this->option('days');
        $this->info("Aggregating daily stats for the last {$days} days...");

        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $count = $service->aggregateDay($date);
            $total += $count;
            $this->line("  {$date->toDateString()}: {$count} member records");
        }

        $this->info("Aggregation complete! Total: {$total} records");
        return self::SUCCESS;
    }
}

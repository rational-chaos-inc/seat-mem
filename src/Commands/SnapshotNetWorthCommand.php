<?php

namespace RCI\MemberEngagement\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RCI\MemberEngagement\Services\NetWorthService;

class SnapshotNetWorthCommand extends Command
{
    protected $signature = 'member-engagement:snapshot-net-worth {--date= : YYYY-MM-DD to record the snapshot against, defaults to today}';

    protected $description = 'Record each registered account\'s asset value, wallet balance and asset regions';

    public function handle(NetWorthService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();

        $this->info("Snapshotting net worth for {$date->toDateString()}...");

        $count = $service->snapshot($date);

        $this->info("Recorded {$count} character snapshots.");

        return self::SUCCESS;
    }
}

<?php

namespace RCI\MemberEngagement\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Services\CapitalHistoryService;
use RCI\MemberEngagement\Services\MonthlyAggregationService;
use RCI\MemberEngagement\Services\NetWorthService;

class FinalizeMonthCommand extends Command
{
    protected $signature = 'member-engagement:finalize-month
                            {--month= : Month to finalize, format YYYY-MM (default: previous month)}
                            {--all : Finalize every month that has daily stats data}';

    protected $description = 'Roll up daily member stats into finalized monthly totals';

    public function handle(
        MonthlyAggregationService $service,
        NetWorthService $netWorth,
        CapitalHistoryService $capitals
    ): int {
        if ($this->option('all')) {
            $months = DailyMemberStat::selectRaw("DISTINCT DATE_FORMAT(date, '%Y-%m-01') as month")
                ->orderBy('month')
                ->pluck('month');

            foreach ($months as $monthStr) {
                $month = Carbon::parse($monthStr);
                $count = $service->finalizeMonth($month);
                $netWorthCount = $netWorth->finalizeMonth($month);
                $capitalCount = $capitals->finalizeMonth($month);
                $this->info("{$month->format('Y-m')}: {$count} member records, "
                    . "{$netWorthCount} net worth records, {$capitalCount} capital classes");
            }

            return self::SUCCESS;
        }

        $monthOption = $this->option('month');
        $month = $monthOption
            ? Carbon::parse($monthOption . '-01')
            : Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $this->info("Finalizing {$month->format('Y-m')}...");
        $count = $service->finalizeMonth($month);
        $netWorthCount = $netWorth->finalizeMonth($month);
        $capitalCount = $capitals->finalizeMonth($month);
        $this->info("Finalized {$count} member records, {$netWorthCount} net worth records "
            . "and {$capitalCount} capital classes for {$month->format('Y-m')}");

        return self::SUCCESS;
    }
}

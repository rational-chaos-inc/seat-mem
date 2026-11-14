<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use RCI\MemberEngagement\Services\CapitalHistoryService;
use RCI\MemberEngagement\Services\CapitalTrackingService;

class RefreshCapitalsCommand extends Command
{
    protected $signature = 'member-engagement:refresh-capitals';

    protected $description = 'Rebuild the cached Capital Tracking dataset';

    public function handle(CapitalTrackingService $service, CapitalHistoryService $history): int
    {
        $this->info('Rebuilding the Capital Tracking cache...');

        $payload = $service->refresh();

        // Assets carry no history, so a reading has to be kept as it is
        // taken - this is the only record the month-on-month figures and
        // the fleet chart will ever have.
        $classes = $history->recordToday($payload['accounts']);

        $hulls = collect($payload['types'])->sum('total');

        $this->info(sprintf(
            'Cached %d hull type(s), %d hull(s), across %d account(s); '
            . 'recorded %d class total(s) for today.',
            count($payload['types']),
            $hulls,
            count($payload['accounts']),
            $classes
        ));

        if (!empty($payload['missing_groups'])) {
            $this->warn(sprintf(
                'Not counted - neither the local SDE nor this plugin can '
                . 'resolve: %s. SeAT pins the SDE to one build, so a class '
                . 'CCP has added recently needs its hull type ids adding to '
                . 'CapitalTrackingService::FALLBACK_HULLS.',
                implode(', ', $payload['missing_groups'])
            ));
        }

        return self::SUCCESS;
    }
}

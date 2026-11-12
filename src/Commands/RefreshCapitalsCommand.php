<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use RCI\MemberEngagement\Services\CapitalTrackingService;

class RefreshCapitalsCommand extends Command
{
    protected $signature = 'member-engagement:refresh-capitals';

    protected $description = 'Rebuild the cached Capital Tracking dataset';

    public function handle(CapitalTrackingService $service): int
    {
        $this->info('Rebuilding the Capital Tracking cache...');

        $payload = $service->refresh();

        $hulls = collect($payload['types'])->sum('total');

        $this->info(sprintf(
            'Cached %d hull type(s), %d hull(s), across %d account(s).',
            count($payload['types']),
            $hulls,
            count($payload['accounts'])
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

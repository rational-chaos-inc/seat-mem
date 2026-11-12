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

        return self::SUCCESS;
    }
}

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
                'Not counted - the local SDE has no record of: %s. Run '
                . 'php artisan eve:update:sde to pick up hull classes CCP has '
                . 'added since the last import.',
                implode(', ', $payload['missing_groups'])
            ));
        }

        return self::SUCCESS;
    }
}

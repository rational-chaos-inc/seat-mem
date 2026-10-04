<?php

namespace RCI\MemberEngagement\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    /**
     * Get the schedules for this plugin.
     */
    public function getSchedules(): array
    {
        return [
            [
                'command' => 'member-engagement:sync --days=2',
                'expression' => '*/15 * * * *',  // Every 15 minutes
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'member-engagement:aggregate --days=3',
                'expression' => '*/30 * * * *',  // Every 30 minutes
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'member-engagement:resolve-names --limit=1000',
                'expression' => '*/20 * * * *',  // Every 20 minutes
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    /**
     * Get the deprecated schedules that should be removed.
     */
    public function getDeprecatedSchedules(): array
    {
        return [
            'member-engagement:collect-activities',
            'member-engagement:check-alerts',
        ];
    }
}

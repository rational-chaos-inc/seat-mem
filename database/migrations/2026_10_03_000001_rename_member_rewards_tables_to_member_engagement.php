<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $renames = [
        'member_rewards_activities' => 'member_engagement_activities',
        'member_rewards_activity_alerts' => 'member_engagement_activity_alerts',
        'member_rewards_activity_aggregation_caches' => 'member_engagement_activity_aggregation_caches',
        'member_rewards_daily_stats' => 'member_engagement_daily_stats',
        'member_rewards_settings' => 'member_engagement_settings',
    ];

    public function up(): void
    {
        foreach ($this->renames as $from => $to) {
            if (Schema::hasTable($from) && !Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->renames) as $from => $to) {
            if (Schema::hasTable($to) && !Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }
};

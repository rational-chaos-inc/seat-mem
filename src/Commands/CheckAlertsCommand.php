<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\ActivityAlert;
use RCI\MemberEngagement\Services\AlertService;

class CheckAlertsCommand extends Command
{
    protected $signature = 'member-engagement:check-alerts {user_id?}';

    protected $description = 'Check and dispatch member engagement alerts';

    public function handle(AlertService $alertService): int
    {
        Log::info("Alert check started");

        try {
            $userId = $this->argument('user_id');

            if ($userId) {
                // Check alerts for specific user
                $alertService->checkAlertsForUser((int) $userId);
                $this->info("Checked alerts for user {$userId}");
            } else {
                // Check alerts for all users with active alerts
                $userIds = ActivityAlert::active()
                    ->distinct()
                    ->pluck('user_id');

                foreach ($userIds as $id) {
                    $alertService->checkAlertsForUser($id);
                }

                $this->info("Checked alerts for {$userIds->count()} users");
            }

            Log::info("Alert check completed");
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Alert check failed", [
                'error' => $e->getMessage(),
            ]);
            $this->error("Alert check failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}

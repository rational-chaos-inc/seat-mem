<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RCI\MemberEngagement\Models\Activity;
use RCI\MemberEngagement\Models\ActivityAlert;

class AlertService
{
    public function __construct(
        private AggregationService $aggregationService,
    ) {
    }

    public function checkAlertsForUser(int $userId): void
    {
        try {
            $alerts = ActivityAlert::forUser($userId)->active()->get();

            foreach ($alerts as $alert) {
                $this->checkAlert($alert);
            }
        } catch (\Exception $e) {
            Log::error("Error checking alerts for user", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function checkAlert(ActivityAlert $alert): void
    {
        try {
            $shouldTrigger = false;
            $triggerContext = [];

            switch ($alert->alert_type) {
                case 'activity_threshold':
                    [$shouldTrigger, $triggerContext] = $this->checkActivityThreshold($alert);
                    break;
                case 'unusual_activity':
                    [$shouldTrigger, $triggerContext] = $this->checkUnusualActivity($alert);
                    break;
                case 'member_alert':
                    [$shouldTrigger, $triggerContext] = $this->checkMemberAlert($alert);
                    break;
            }

            if ($shouldTrigger) {
                $this->dispatchAlert($alert, $triggerContext);
            }
        } catch (\Exception $e) {
            Log::error("Error checking alert", [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function checkActivityThreshold(ActivityAlert $alert): array
    {
        $timeWindow = $alert->getTimeWindow() ?? 'month';
        $threshold = $alert->getValueThreshold();
        $types = $alert->getActivityTypeFilter();

        if ($threshold === null || $threshold <= 0) {
            return [false, []];
        }

        // Get activity totals
        $total = 0;

        if (empty($types)) {
            $agg = $this->aggregationService->aggregateForUser($alert->user_id, $timeWindow);
            $total = $agg['total'];
        } else {
            foreach ($types as $type) {
                $agg = $this->aggregationService->aggregateForUser($alert->user_id, $timeWindow, $type);
                $total += $agg['total'];
            }
        }

        $triggered = $total >= $threshold;

        return [
            $triggered,
            [
                'threshold' => $threshold,
                'actual' => $total,
                'time_window' => $timeWindow,
                'activity_types' => $types,
            ],
        ];
    }

    private function checkUnusualActivity(ActivityAlert $alert): array
    {
        // Get recent activity count
        $recentHours = 24;
        $recentActivities = Activity::where('user_id', $alert->user_id)
            ->where('activity_timestamp', '>=', Carbon::now()->subHours($recentHours))
            ->count();

        // Unusual if > 2x normal daily average
        $avgDaily = Activity::where('user_id', $alert->user_id)
            ->where('activity_timestamp', '>=', Carbon::now()->subDays(30))
            ->count() / 30;

        $triggered = $recentActivities > ($avgDaily * 2);

        return [
            $triggered,
            [
                'recent_count' => $recentActivities,
                'average_daily' => round($avgDaily, 2),
                'hours' => $recentHours,
            ],
        ];
    }

    private function checkMemberAlert(ActivityAlert $alert): array
    {
        // Check for any new activities in the last hour
        $newActivities = Activity::where('user_id', $alert->user_id)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        return [$newActivities > 0, ['new_activities' => $newActivities]];
    }

    private function dispatchAlert(ActivityAlert $alert, array $context): void
    {
        try {
            $user = config('auth.providers.users.model')::find($alert->user_id);

            if (!$user) {
                return;
            }

            switch ($alert->notification_method) {
                case 'in_app':
                    $this->sendInAppNotification($user, $alert, $context);
                    break;
                case 'email':
                    $this->sendEmailNotification($user, $alert, $context);
                    break;
                case 'webhook':
                    $this->sendWebhookNotification($user, $alert, $context);
                    break;
            }

            $alert->markAsTriggered();

            Log::info("Alert dispatched", [
                'alert_id' => $alert->id,
                'user_id' => $user->id,
                'method' => $alert->notification_method,
            ]);
        } catch (\Exception $e) {
            Log::error("Error dispatching alert", [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendInAppNotification($user, ActivityAlert $alert, array $context): void
    {
        // Use SeAT's notification system
        $message = $this->buildAlertMessage($alert, $context);

        // TODO: Integrate with SeAT's notification system
        Log::info("In-app notification (stub)", [
            'user_id' => $user->id,
            'message' => $message,
        ]);
    }

    private function sendEmailNotification($user, ActivityAlert $alert, array $context): void
    {
        try {
            // TODO: Create and send email notification
            $message = $this->buildAlertMessage($alert, $context);

            Log::info("Email notification sent", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send email notification", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWebhookNotification($user, ActivityAlert $alert, array $context): void
    {
        try {
            // Get webhook URL from conditions
            $webhookUrl = $alert->conditions['webhook_url'] ?? null;

            if (!$webhookUrl) {
                return;
            }

            $payload = [
                'alert_id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'user_id' => $user->id,
                'timestamp' => now()->toIso8601String(),
                'context' => $context,
            ];

            // TODO: Send webhook POST request
            Log::info("Webhook notification sent", [
                'user_id' => $user->id,
                'webhook_url' => $webhookUrl,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send webhook notification", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildAlertMessage(ActivityAlert $alert, array $context): string
    {
        return match ($alert->alert_type) {
            'activity_threshold' => sprintf(
                "Activity threshold alert: Reached %s ISK (threshold: %s ISK) in %s",
                number_format($context['actual'], 2),
                number_format($context['threshold'], 2),
                $context['time_window']
            ),
            'unusual_activity' => sprintf(
                "Unusual activity detected: %d activities in 24 hours (normal: %s/day)",
                $context['recent_count'],
                $context['average_daily']
            ),
            'member_alert' => sprintf(
                "New activities detected: %d new activit%s",
                $context['new_activities'],
                $context['new_activities'] === 1 ? 'y' : 'ies'
            ),
            default => 'Activity alert triggered',
        };
    }

    public function sendTestNotification(ActivityAlert $alert): void
    {
        try {
            $user = config('auth.providers.users.model')::find($alert->user_id);

            if (!$user) {
                throw new \Exception("User not found");
            }

            $testContext = [
                'test' => true,
                'message' => 'This is a test notification from Member Engagement Module',
            ];

            $this->dispatchAlert($alert, $testContext);
        } catch (\Exception $e) {
            Log::error("Error sending test notification", [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getAllAlertsForUser(int $userId)
    {
        return ActivityAlert::forUser($userId)->get();
    }

    public function disableAlert(int $alertId): void
    {
        ActivityAlert::find($alertId)?->update(['is_active' => false]);
    }

    public function enableAlert(int $alertId): void
    {
        ActivityAlert::find($alertId)?->update(['is_active' => true]);
    }
}

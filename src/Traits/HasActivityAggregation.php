<?php

namespace RCI\MemberEngagement\Traits;

use Carbon\Carbon;
use RCI\MemberEngagement\Models\Activity;

trait HasActivityAggregation
{
    public function getTotalActivityValue(string $timeWindow = 'month', ?string $activityType = null): float
    {
        $query = Activity::where('character_id', $this->character_id ?? $this->id);

        if ($activityType) {
            $query->where('activity_type', $activityType);
        }

        $period = $this->getTimePeriod($timeWindow);
        $query->whereBetween('activity_timestamp', [$period['start'], $period['end']]);

        return $query->get()
            ->sum(fn($a) => $a->getValueFromMetadata() ?? 0);
    }

    public function getActivityCount(string $timeWindow = 'month', ?string $activityType = null): int
    {
        $query = Activity::where('character_id', $this->character_id ?? $this->id);

        if ($activityType) {
            $query->where('activity_type', $activityType);
        }

        $period = $this->getTimePeriod($timeWindow);
        $query->whereBetween('activity_timestamp', [$period['start'], $period['end']]);

        return $query->count();
    }

    public function getActivityByType(string $timeWindow = 'month'): array
    {
        $query = Activity::where('character_id', $this->character_id ?? $this->id);

        $period = $this->getTimePeriod($timeWindow);
        $query->whereBetween('activity_timestamp', [$period['start'], $period['end']]);

        return $query->get()
            ->groupBy('activity_type')
            ->map(function ($activities) {
                return [
                    'count' => $activities->count(),
                    'total' => $activities->sum(fn($a) => $a->getValueFromMetadata() ?? 0),
                ];
            })
            ->toArray();
    }

    public function getRecentActivities(int $limit = 10, ?string $activityType = null): array
    {
        $query = Activity::where('character_id', $this->character_id ?? $this->id)
            ->orderBy('activity_timestamp', 'desc')
            ->limit($limit);

        if ($activityType) {
            $query->where('activity_type', $activityType);
        }

        return $query->get()->toArray();
    }

    private function getTimePeriod(string $timeWindow): array
    {
        $end = Carbon::now()->endOfDay();

        return match ($timeWindow) {
            'day' => ['start' => $end->clone()->startOfDay(), 'end' => $end],
            'week' => ['start' => $end->clone()->startOfWeek(), 'end' => $end],
            'month' => ['start' => $end->clone()->startOfMonth(), 'end' => $end],
            'quarter' => ['start' => $end->clone()->startOfQuarter(), 'end' => $end],
            'year' => ['start' => $end->clone()->startOfYear(), 'end' => $end],
            default => ['start' => $end->clone()->subDays(30), 'end' => $end],
        };
    }
}

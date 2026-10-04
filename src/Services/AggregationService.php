<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\Activity;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MemberEngagementSetting;

class AggregationService
{
    private const DEFAULT_FLEET_MIN_SIZE = 5;

    public function aggregateDay(Carbon $date, ?int $corporationId = null): int
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $query = Activity::whereBetween('activity_timestamp', [$startOfDay, $endOfDay]);

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
        }

        $activities = $query->get();

        // Filter out activities without a corporation_id
        $activities = $activities->filter(fn($a) => !empty($a->corporation_id));

        // Get login data from corporation_member_trackings
        $corpIds = $activities->pluck('corporation_id')->unique();
        $loginData = DB::table('corporation_member_trackings')
            ->whereIn('corporation_id', $corpIds)
            ->whereBetween('logon_date', [$startOfDay, $endOfDay])
            ->pluck('character_id')
            ->toArray();

        // Each corporation may configure its own minimum fleet size for the
        // Fleet Participation metric; fall back to the default when a
        // corporation has no settings row yet.
        $fleetMinSizeByCorp = MemberEngagementSetting::whereIn('corporation_id', $corpIds)
            ->whereNotNull('fleet_participation_min_size')
            ->pluck('fleet_participation_min_size', 'corporation_id');

        // Group by corporation and character
        $grouped = $activities->groupBy(function ($activity) {
            return $activity->corporation_id . '|' . $activity->character_id;
        });

        $count = 0;
        foreach ($grouped as $key => $groupedActivities) {
            [$corpId, $charId] = explode('|', $key);

            $stats = [
                'date' => $date->toDateString(),
                'corporation_id' => $corpId,
                'character_id' => $charId,
                'logged_in' => in_array($charId, $loginData) || $groupedActivities->count() > 0,
                'mining_quantity' => 0,
                'mining_value' => 0,
                'pve_bounty_amount' => 0,
                'industry_tax_amount' => 0,
                'pvp_kills' => 0,
                'pvp_losses' => 0,
                'fleet_participation' => 0,
            ];

            // Aggregate by activity type
            foreach ($groupedActivities as $activity) {
                match ($activity->activity_type) {
                    'mining' => $stats['mining_quantity'] += $activity->metadata['quantity'] ?? 0,
                    'pve_bounty_tax' => $stats['pve_bounty_amount'] += $activity->metadata['amount'] ?? 0,
                    'industry_tax' => $stats['industry_tax_amount'] += $activity->metadata['amount'] ?? 0,
                    'pvp_kill' => $stats['pvp_kills'] += 1,
                    'pvp_loss' => $stats['pvp_losses'] += 1,
                    default => null,
                };
            }

            // Fleet participation: killmails with N+ total attackers (per-corp
            // configurable minimum), deduplicated to 1 per hour
            $fleetMinSize = $fleetMinSizeByCorp[$corpId] ?? self::DEFAULT_FLEET_MIN_SIZE;
            $stats['fleet_participation'] = $this->countFleetParticipationByHour($groupedActivities, $fleetMinSize);

            DailyMemberStat::updateOrCreate(
                [
                    'date' => $date->toDateString(),
                    'corporation_id' => $corpId,
                    'character_id' => $charId,
                ],
                $stats
            );

            $count++;
        }

        return $count;
    }

    private function countFleetParticipationByHour($activities, int $minSize): int
    {
        $fleetKills = $activities->filter(function ($activity) use ($minSize) {
            return $activity->activity_type === 'pvp_kill'
                && ($activity->metadata['attacker_count'] ?? 1) >= $minSize;
        });

        $hourBuckets = [];
        foreach ($fleetKills as $activity) {
            $hour = $activity->activity_timestamp->copy()->startOfHour()->toDateTimeString();
            $hourBuckets[$hour] = true;
        }

        return count($hourBuckets);
    }
}

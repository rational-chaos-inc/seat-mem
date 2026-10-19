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

        // Logins are tracked independently of trackable wallet/kill/mining
        // activity - a character who logs in without generating any
        // trackable activity that day would otherwise never get a "logged
        // in" day recorded at all, which is exactly what was happening
        // before this, since 'logged_in' was only ever set on rows that
        // already existed because of tracked activity.
        //
        // character_onlines is SeAT core's sync of ESI's
        // /characters/{id}/online/ endpoint (per-character, using that
        // character's own token) - unlike corporation_member_trackings
        // (corp-wide membertracking, which can miss a registered character
        // entirely depending on corp roles/bucket coverage), this is
        // scoped exactly to the character and always available for every
        // registered one. It still only ever holds the MOST RECENT known
        // login/logout though (ESI exposes no history), hence the sticky
        // logged_in handling below.
        $registeredCharacterIds = DB::table('refresh_tokens')->whereNull('deleted_at')->pluck('character_id');

        $onlineRows = DB::table('character_onlines')
            ->whereIn('character_id', $registeredCharacterIds)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('last_login', [$startOfDay, $endOfDay])
                    ->orWhereBetween('last_logout', [$startOfDay, $endOfDay]);
            })
            ->get(['character_id']);

        // character_onlines has no corporation_id of its own - resolve it
        // via SeAT core's character_affiliations (bulk-synced for every
        // known character, so it covers characters with zero activity too,
        // unlike this plugin's own slower-building affiliation cache).
        $corpByCharacter = DB::table('character_affiliations')
            ->whereIn('character_id', $onlineRows->pluck('character_id'))
            ->pluck('corporation_id', 'character_id');

        $logins = $onlineRows
            ->filter(fn($row) => isset($corpByCharacter[$row->character_id]))
            ->map(fn($row) => $corpByCharacter[$row->character_id] . '|' . $row->character_id)
            ->unique();

        if ($corporationId) {
            $logins = $logins->filter(fn($key) => str_starts_with($key, $corporationId . '|'));
        }

        // Fleet Participation's minimum size is a single global setting,
        // applying regardless of corporation.
        $fleetMinSize = MemberEngagementSetting::current()->fleet_participation_min_size
            ?? self::DEFAULT_FLEET_MIN_SIZE;

        // Group by corporation and character
        $grouped = $activities->groupBy(function ($activity) {
            return $activity->corporation_id . '|' . $activity->character_id;
        });

        // Every key with activity and/or a detected login this day needs a row.
        $keys = $grouped->keys()->merge($logins)->unique();

        // character_onlines is a continuously-overwritten snapshot, so
        // re-checking it on a later run can miss a login it already caught
        // for this day (the character logged in again since, moving the
        // snapshot on). Once a day is marked logged_in, keep it that way
        // rather than letting a later recompute flip it back to false.
        $previouslyLoggedIn = DailyMemberStat::where('date', $date->toDateString())
            ->where('logged_in', true)
            ->get(['corporation_id', 'character_id'])
            ->map(fn($row) => $row->corporation_id . '|' . $row->character_id)
            ->flip();

        $count = 0;
        foreach ($keys as $key) {
            [$corpId, $charId] = explode('|', $key);
            $groupedActivities = $grouped->get($key, collect());

            $stats = [
                'date' => $date->toDateString(),
                'corporation_id' => $corpId,
                'character_id' => $charId,
                'logged_in' => $logins->contains($key) || $previouslyLoggedIn->has($key),
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

            // Fleet participation: killmails with N+ total attackers
            // (globally configurable minimum), deduplicated to 1 per hour
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

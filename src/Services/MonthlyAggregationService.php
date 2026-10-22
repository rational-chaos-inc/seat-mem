<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MonthlyMemberStat;

/**
 * Rolls member_engagement_daily_stats up into one finalized row per
 * corporation+character+month. Intended to run once a completed month
 * is over (the 1st of the following month) - see FinalizeMonthCommand.
 */
class MonthlyAggregationService
{
    public function finalizeMonth(Carbon $month, ?int $corporationId = null): int
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $query = DailyMemberStat::whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
        }

        $rows = $query->get();

        $grouped = $rows->groupBy(fn($r) => $r->corporation_id . '|' . $r->character_id);

        $count = 0;
        foreach ($grouped as $key => $dayRows) {
            [$corpId, $charId] = explode('|', $key);

            MonthlyMemberStat::updateOrCreate(
                [
                    'month' => $start->toDateString(),
                    'corporation_id' => $corpId,
                    'character_id' => $charId,
                ],
                [
                    'mining_quantity' => $dayRows->sum('mining_quantity'),
                    'pve_bounty_amount' => $dayRows->sum('pve_bounty_amount'),
                    'industry_tax_amount' => $dayRows->sum('industry_tax_amount'),
                    'pvp_kills' => $dayRows->sum('pvp_kills'),
                    'pvp_losses' => $dayRows->sum('pvp_losses'),
                    'fleet_participation' => $dayRows->sum('fleet_participation'),
                    'mining_tax_amount' => $dayRows->sum('mining_tax_amount'),
                    'isk_destroyed' => $dayRows->sum('isk_destroyed'),
                    'isk_lost' => $dayRows->sum('isk_lost'),
                ]
            );
            $count++;
        }

        return $count;
    }
}

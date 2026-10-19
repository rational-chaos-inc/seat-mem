<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MonthlyMemberStat;

/**
 * Builds the three Rankings dashboard leaderboards (tax income, PvP kills,
 * fleet participation) for a given month, consolidating linked alts under
 * their account's main character.
 */
class RankingService
{
    private const MAX_RESULTS = 25;

    public function __construct(
        private AccountConsolidationService $consolidation,
        private EntityResolutionService $entityResolution,
    ) {
    }

    /**
     * @return array{tax: array, kills: array, fleet: array}
     */
    public function buildRankings(Carbon $month, bool $isCurrentMonth): array
    {
        $rows = $isCurrentMonth
            ? $this->liveRowsForMonth($month)
            : $this->finalizedRowsForMonth($month);

        if ($rows->isEmpty() && !$isCurrentMonth) {
            // Not finalized yet (e.g. feature just added) - compute live as a fallback.
            $rows = $this->liveRowsForMonth($month);
        }

        if ($rows->isEmpty()) {
            return ['tax' => [], 'kills' => [], 'fleet' => []];
        }

        $groupMap = $this->consolidation->resolveGroups($rows->pluck('character_id')->unique()->all());

        // Scoped to registered SeAT members and their known alts only -
        // characters with no account link are excluded, not shown solo.
        $rows = $rows->filter(fn($r) => isset($groupMap[$r->character_id]));

        if ($rows->isEmpty()) {
            return ['tax' => [], 'kills' => [], 'fleet' => []];
        }

        $consolidated = $rows
            ->groupBy(fn($r) => $groupMap[$r->character_id])
            ->map(function (Collection $groupRows, $groupCharId) {
                return (object) [
                    'character_id' => (int) $groupCharId,
                    'corporation_id' => $groupRows->first()->corporation_id,
                    'metadata' => [],
                    'member_count' => $groupRows->pluck('character_id')->unique()->count(),
                    'pve_bounty_amount' => (float) $groupRows->sum('pve_bounty_amount'),
                    'industry_tax_amount' => (float) $groupRows->sum('industry_tax_amount'),
                    'tax_income' => (float) ($groupRows->sum('pve_bounty_amount') + $groupRows->sum('industry_tax_amount')),
                    'pvp_kills' => (int) $groupRows->sum('pvp_kills'),
                    'fleet_participation' => (int) $groupRows->sum('fleet_participation'),
                ];
            })
            ->values();

        $this->entityResolution->attachDisplayNames($consolidated);

        return [
            'tax' => $consolidated->filter(fn($r) => $r->tax_income > 0)
                ->sortByDesc('tax_income')->take(self::MAX_RESULTS)->values()->all(),
            'kills' => $consolidated->filter(fn($r) => $r->pvp_kills > 0)
                ->sortByDesc('pvp_kills')->take(self::MAX_RESULTS)->values()->all(),
            'fleet' => $consolidated->filter(fn($r) => $r->fleet_participation > 0)
                ->sortByDesc('fleet_participation')->take(self::MAX_RESULTS)->values()->all(),
        ];
    }

    private function liveRowsForMonth(Carbon $month): Collection
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return DailyMemberStat::whereBetween('date', [$start, $end])
            ->get()
            ->groupBy(fn($r) => $r->corporation_id . '|' . $r->character_id)
            ->map(function (Collection $dayRows) {
                $first = $dayRows->first();
                return (object) [
                    'character_id' => $first->character_id,
                    'corporation_id' => $first->corporation_id,
                    'pve_bounty_amount' => $dayRows->sum('pve_bounty_amount'),
                    'industry_tax_amount' => $dayRows->sum('industry_tax_amount'),
                    'pvp_kills' => $dayRows->sum('pvp_kills'),
                    'fleet_participation' => $dayRows->sum('fleet_participation'),
                ];
            })
            ->values();
    }

    private function finalizedRowsForMonth(Carbon $month): Collection
    {
        return MonthlyMemberStat::where('month', $month->copy()->startOfMonth()->toDateString())->get();
    }
}

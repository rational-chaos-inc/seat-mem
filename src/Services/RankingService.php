<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MonthlyMemberStat;

/**
 * Builds the Corporation View leaderboards (PvE Bounty & Tax, Industry Tax,
 * PvP kills, fleet participation) for a given month, consolidating linked
 * alts under their account's main character. Also builds the equivalent
 * single-account figures for the My Activities page.
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
     * @return array{pve_bounty: array, industry_tax: array, kills: array, fleet: array}
     */
    public function buildRankings(Carbon $month, bool $isCurrentMonth): array
    {
        $rows = $this->rowsForMonth($month, $isCurrentMonth);

        if ($rows->isEmpty()) {
            return ['pve_bounty' => [], 'industry_tax' => [], 'kills' => [], 'fleet' => []];
        }

        $groupMap = $this->consolidation->resolveGroups($rows->pluck('character_id')->unique()->all());

        // Scoped to registered SeAT members and their known alts only -
        // characters with no account link are excluded, not shown solo.
        $rows = $rows->filter(fn($r) => isset($groupMap[$r->character_id]));

        if ($rows->isEmpty()) {
            return ['pve_bounty' => [], 'industry_tax' => [], 'kills' => [], 'fleet' => []];
        }

        $consolidated = $this->consolidate($rows, $groupMap);

        $this->entityResolution->attachDisplayNames($consolidated);

        return [
            'pve_bounty' => $consolidated->filter(fn($r) => $r->pve_bounty_amount > 0)
                ->sortByDesc('pve_bounty_amount')->take(self::MAX_RESULTS)->values()->all(),
            'industry_tax' => $consolidated->filter(fn($r) => $r->industry_tax_amount > 0)
                ->sortByDesc('industry_tax_amount')->take(self::MAX_RESULTS)->values()->all(),
            'kills' => $consolidated->filter(fn($r) => $r->pvp_kills > 0)
                ->sortByDesc('pvp_kills')->take(self::MAX_RESULTS)->values()->all(),
            'fleet' => $consolidated->filter(fn($r) => $r->fleet_participation > 0)
                ->sortByDesc('fleet_participation')->take(self::MAX_RESULTS)->values()->all(),
        ];
    }

    /**
     * The same four figures as buildRankings(), but summed across just the
     * given character IDs (one SeAT account's main + linked alts) rather
     * than ranked against the rest of the corporation.
     */
    public function buildOwnStats(array $characterIds, Carbon $month, bool $isCurrentMonth): object
    {
        $characterIds = array_map('intval', $characterIds);
        $rows = $this->rowsForMonth($month, $isCurrentMonth)
            ->filter(fn($r) => in_array((int) $r->character_id, $characterIds, true));

        return (object) [
            'pve_bounty_amount' => (float) $rows->sum('pve_bounty_amount'),
            'industry_tax_amount' => (float) $rows->sum('industry_tax_amount'),
            'pvp_kills' => (int) $rows->sum('pvp_kills'),
            'fleet_participation' => (int) $rows->sum('fleet_participation'),
        ];
    }

    /**
     * Resolve a `?month=YYYY-MM` request value to a month start + whether
     * it's the current (still live) month, clamped so navigating into the
     * future isn't possible.
     *
     * @return array{0: Carbon, 1: bool}
     */
    public function resolveMonth(?string $monthParam): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $month = $monthParam ? Carbon::parse($monthParam . '-01')->startOfMonth() : $currentMonthStart->copy();

        if ($month->greaterThan($currentMonthStart)) {
            $month = $currentMonthStart->copy();
        }

        return [$month, $month->equalTo($currentMonthStart)];
    }

    /**
     * @return Carbon[] every calendar month with recorded daily stats data,
     *         through the current month, most recent first.
     */
    public function availableMonths(): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $earliestDate = DB::table('member_engagement_daily_stats')->min('date');

        if (!$earliestDate) {
            return [$currentMonthStart];
        }

        $months = [];
        $cursor = Carbon::parse($earliestDate)->startOfMonth();

        while ($cursor->lte($currentMonthStart)) {
            $months[] = $cursor->copy();
            $cursor->addMonthNoOverflow();
        }

        return array_reverse($months);
    }

    private function rowsForMonth(Carbon $month, bool $isCurrentMonth): Collection
    {
        $rows = $isCurrentMonth
            ? $this->liveRowsForMonth($month)
            : $this->finalizedRowsForMonth($month);

        if ($rows->isEmpty() && !$isCurrentMonth) {
            // Not finalized yet (e.g. feature just added, or current month
            // viewed right after rolling over) - compute live as a fallback.
            $rows = $this->liveRowsForMonth($month);
        }

        return $rows;
    }

    private function consolidate(Collection $rows, array $groupMap): Collection
    {
        return $rows
            ->groupBy(fn($r) => $groupMap[$r->character_id])
            ->map(function (Collection $groupRows, $groupCharId) {
                return (object) [
                    'character_id' => (int) $groupCharId,
                    'corporation_id' => $groupRows->first()->corporation_id,
                    'metadata' => [],
                    'member_count' => $groupRows->pluck('character_id')->unique()->count(),
                    'pve_bounty_amount' => (float) $groupRows->sum('pve_bounty_amount'),
                    'industry_tax_amount' => (float) $groupRows->sum('industry_tax_amount'),
                    'pvp_kills' => (int) $groupRows->sum('pvp_kills'),
                    'fleet_participation' => (int) $groupRows->sum('fleet_participation'),
                ];
            })
            ->values();
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

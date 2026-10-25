<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Models\MonthlyMemberStat;

/**
 * Builds the Corporation View leaderboards (tax contributions and PvP) for
 * a given month, consolidating linked alts under their account's main
 * character. Also builds the equivalent single-account figures for the My
 * Activities page, and the Heartbeat login list.
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
     * One leaderboard per entry in RANKED_METRICS, each a list of
     * consolidated accounts sorted high to low with zero-value entries
     * dropped.
     *
     * @return array<string, array>
     */
    public function buildRankings(Carbon $month, bool $isCurrentMonth): array
    {
        $rows = $this->rowsForMonth($month, $isCurrentMonth);

        if ($rows->isEmpty()) {
            return $this->emptyRankings();
        }

        $groupMap = $this->consolidation->resolveGroups($rows->pluck('character_id')->unique()->all());

        // Scoped to registered SeAT members and their known alts only -
        // characters with no account link are excluded, not shown solo.
        $rows = $rows->filter(fn($r) => isset($groupMap[$r->character_id]));

        if ($rows->isEmpty()) {
            return $this->emptyRankings();
        }

        $consolidated = $this->consolidate($rows, $groupMap);
        $this->attachFleetParticipation($consolidated, $month, $isCurrentMonth, $groupMap);

        $this->entityResolution->attachDisplayNames($consolidated);

        return collect(self::RANKED_METRICS)
            ->mapWithKeys(fn($field, $key) => [
                $key => $consolidated->filter(fn($r) => $r->{$field} > 0)
                    ->sortByDesc($field)->take(self::MAX_RESULTS)->values()->all(),
            ])
            ->all();
    }

    /**
     * Leaderboard key => the consolidated field it ranks on.
     */
    private const RANKED_METRICS = [
        'pve_bounty' => 'pve_bounty_amount',
        'industry_tax' => 'industry_tax_amount',
        'mining_tax' => 'mining_tax_amount',
        'kills' => 'pvp_kills',
        'isk_destroyed' => 'isk_destroyed',
        'losses' => 'pvp_losses',
        'isk_lost' => 'isk_lost',
        'fleet' => 'fleet_count',
    ];

    private function emptyRankings(): array
    {
        return array_fill_keys(array_keys(self::RANKED_METRICS), []);
    }

    /**
     * The same figures as buildRankings(), but summed across just the
     * given character IDs (one SeAT account's main + linked alts) rather
     * than ranked against the rest of the corporation.
     */
    public function buildOwnStats(array $characterIds, Carbon $month, bool $isCurrentMonth): object
    {
        $characterIds = array_map('intval', $characterIds);
        $rows = $this->rowsForMonth($month, $isCurrentMonth)
            ->filter(fn($r) => in_array((int) $r->character_id, $characterIds, true));

        $fleet = $this->fleetBuckets($characterIds, $month, $isCurrentMonth);
        $buckets = collect($fleet)->flatten()->unique();

        return (object) [
            'pve_bounty_amount' => (float) $rows->sum('pve_bounty_amount'),
            'industry_tax_amount' => (float) $rows->sum('industry_tax_amount'),
            'mining_tax_amount' => (float) $rows->sum('mining_tax_amount'),
            'pvp_kills' => (int) $rows->sum('pvp_kills'),
            'pvp_losses' => (int) $rows->sum('pvp_losses'),
            'isk_destroyed' => (float) $rows->sum('isk_destroyed'),
            'isk_lost' => (float) $rows->sum('isk_lost'),
            // Fleets the account was in, counting a fleet once however
            // many of its characters were there; instances counts them all.
            'fleet_count' => $buckets->count(),
            'fleet_instances' => collect($fleet)->sum(fn($hours) => count($hours)),
        ];
    }

    /**
     * One row per registered SeAT account (main + linked alts), showing how
     * many distinct days in the given month they logged in on any of their
     * characters, plus when they last logged in overall - so an account
     * with zero days this month still shows up with a real "last seen"
     * date rather than silently vanishing from the list. Sorted so the
     * least active accounts surface first.
     *
     * @return object[]
     */
    public function buildHeartbeat(Carbon $month, bool $isCurrentMonth): array
    {
        $groups = $this->consolidation->allAccountGroups();

        if (empty($groups)) {
            return [];
        }

        $charToMain = [];
        foreach ($groups as $mainId => $characterIds) {
            foreach ($characterIds as $characterId) {
                $charToMain[$characterId] = $mainId;
            }
        }

        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $isCurrentMonth
            ? Carbon::now()->toDateString()
            : $month->copy()->endOfMonth()->toDateString();

        $monthLoginDates = DailyMemberStat::where('logged_in', true)
            ->whereIn('character_id', array_keys($charToMain))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get(['character_id', 'date'])
            ->groupBy(fn($row) => $charToMain[$row->character_id]);

        $lastLoginByCharacter = DailyMemberStat::where('logged_in', true)
            ->whereIn('character_id', array_keys($charToMain))
            ->selectRaw('character_id, MAX(date) as last_date')
            ->groupBy('character_id')
            ->pluck('last_date', 'character_id');

        $today = Carbon::now()->toDateString();

        $heartbeat = collect($groups)->map(function ($characterIds, $mainId) use ($monthLoginDates, $lastLoginByCharacter, $today) {
            $daysThisMonth = ($monthLoginDates->get($mainId) ?? collect())
                ->pluck('date')
                ->map(fn($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
                ->unique()
                ->count();

            $lastLogin = collect($characterIds)
                ->map(fn($id) => $lastLoginByCharacter[$id] ?? null)
                ->filter()
                ->map(fn($d) => Carbon::parse($d))
                ->sort()
                ->last();

            return (object) [
                'character_id' => (int) $mainId,
                'corporation_id' => null,
                'metadata' => [],
                'member_count' => count($characterIds),
                'days_logged_in' => $daysThisMonth,
                'last_login_date' => $lastLogin,
                'logged_in_today' => $lastLogin && $lastLogin->toDateString() === $today,
            ];
        })->values();

        $this->entityResolution->attachDisplayNames($heartbeat);

        return $heartbeat->sortBy([
            ['days_logged_in', 'asc'],
            fn($a, $b) => ($a->last_login_date?->timestamp ?? 0) <=> ($b->last_login_date?->timestamp ?? 0),
        ])->values()->all();
    }

    /**
     * Fleet participation, counted per account rather than per character.
     *
     * Five of an account's characters in the same fleet is one fleet, not
     * five - so the ranked figure counts distinct hour buckets across the
     * whole account. "Instances" keeps the old per-character total beside
     * it, which is what shows how many hulls they actually brought.
     *
     * @param  array<int, int>  $groupMap character_id => main character_id
     */
    private function attachFleetParticipation(
        Collection $accounts,
        Carbon $month,
        bool $isCurrentMonth,
        array $groupMap
    ): void {
        $hoursByCharacter = $this->fleetBuckets(array_keys($groupMap), $month, $isCurrentMonth);

        $perAccount = [];
        foreach ($hoursByCharacter as $characterId => $hours) {
            $mainId = $groupMap[$characterId] ?? null;
            if ($mainId === null) {
                continue;
            }

            $perAccount[$mainId]['hours'] = array_merge($perAccount[$mainId]['hours'] ?? [], $hours);
            $perAccount[$mainId]['instances'] = ($perAccount[$mainId]['instances'] ?? 0) + count($hours);
        }

        foreach ($accounts as $account) {
            $entry = $perAccount[$account->character_id] ?? null;

            $account->fleet_count = $entry ? count(array_unique($entry['hours'])) : 0;
            $account->fleet_instances = $entry ? $entry['instances'] : 0;
        }
    }

    /**
     * The distinct hours in which each character was on a killmail big
     * enough to count as a fleet. Read from the activity rows rather than
     * the daily totals, because a summed count can't be deduplicated.
     *
     * @param  int[]  $characterIds
     * @return array<int, string[]> character_id => hour buckets
     */
    private function fleetBuckets(array $characterIds, Carbon $month, bool $isCurrentMonth): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $minSize = (int) (MemberEngagementSetting::current()->fleet_participation_min_size ?? 5);
        $start = $month->copy()->startOfMonth();
        $end = $isCurrentMonth ? Carbon::now()->endOfDay() : $month->copy()->endOfMonth();

        return DB::table('member_engagement_activities')
            ->where('activity_type', 'pvp_kill')
            ->whereBetween('activity_timestamp', [$start, $end])
            ->whereIn('character_id', $characterIds)
            ->whereRaw("CAST(JSON_EXTRACT(metadata, '$.attacker_count') AS UNSIGNED) >= ?", [$minSize])
            ->selectRaw("character_id, DATE_FORMAT(activity_timestamp, '%Y-%m-%d %H') AS hour_bucket")
            ->groupBy('character_id', 'hour_bucket')
            ->get()
            ->groupBy('character_id')
            ->map(fn($rows) => $rows->pluck('hour_bucket')->all())
            ->mapWithKeys(fn($hours, $characterId) => [(int) $characterId => $hours])
            ->all();
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
                    'mining_tax_amount' => (float) $groupRows->sum('mining_tax_amount'),
                    'pvp_kills' => (int) $groupRows->sum('pvp_kills'),
                    'pvp_losses' => (int) $groupRows->sum('pvp_losses'),
                    'isk_destroyed' => (float) $groupRows->sum('isk_destroyed'),
                    'isk_lost' => (float) $groupRows->sum('isk_lost'),
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
                    'mining_tax_amount' => $dayRows->sum('mining_tax_amount'),
                    'pvp_kills' => $dayRows->sum('pvp_kills'),
                    'pvp_losses' => $dayRows->sum('pvp_losses'),
                    'isk_destroyed' => $dayRows->sum('isk_destroyed'),
                    'isk_lost' => $dayRows->sum('isk_lost'),
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

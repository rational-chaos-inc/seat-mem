<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Models\NetWorthDaily;
use RCI\MemberEngagement\Models\NetWorthMonthly;

/**
 * Snapshots each registered character's net worth (assets + wallet) and
 * where those assets physically sit, then consolidates it per account for
 * the Corporation View.
 *
 * Assets don't record a region. The overwhelming majority sit nested
 * inside ships and containers, and ESI reports Upwell structures as
 * location_type "item" too, so the containment tree has to be walked up to
 * whatever station/structure/system actually holds it before a region can
 * be resolved. That's done in one recursive CTE rather than row by row.
 */
class NetWorthService
{
    /** Assets whose location can't be resolved are grouped under this key. */
    public const UNKNOWN_REGION = 'unknown';

    /** Guards against a cycle in the containment tree. */
    private const MAX_NESTING_DEPTH = 20;

    /** invCategories.categoryID for ships. */
    private const SHIP_CATEGORY_ID = 6;

    public function __construct(
        private AccountConsolidationService $consolidation,
        private EntityResolutionService $entityResolution,
    ) {
    }

    /**
     * Value every registered character's assets and wallet as they stand
     * right now, and write one row per character for the given date.
     */
    public function snapshot(Carbon $date): int
    {
        $characterIds = DB::table('refresh_tokens')
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($characterIds->isEmpty()) {
            return 0;
        }

        $assetsByCharacter = $this->assetValuesByRegion($characterIds->all());

        $wallets = DB::table('character_wallet_balances')
            ->whereIn('character_id', $characterIds)
            ->pluck('balance', 'character_id');

        $corporations = DB::table('character_affiliations')
            ->whereIn('character_id', $characterIds)
            ->pluck('corporation_id', 'character_id');

        $count = 0;
        foreach ($characterIds as $characterId) {
            $regions = $assetsByCharacter[$characterId] ?? [];

            NetWorthDaily::updateOrCreate(
                [
                    'date' => $date->toDateString(),
                    'character_id' => $characterId,
                ],
                [
                    'corporation_id' => $corporations[$characterId] ?? null,
                    'asset_value' => array_sum($regions),
                    'wallet_balance' => (float) ($wallets[$characterId] ?? 0),
                    'regions' => $regions,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Copies the last snapshot taken within the month into the monthly
     * table, so later months have something to compare against. Net worth
     * is a balance, so this is the month's closing value - not a sum of
     * the days.
     */
    public function finalizeMonth(Carbon $month): int
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $lastDate = NetWorthDaily::whereBetween('date', [$start, $end])->max('date');

        if (!$lastDate) {
            return 0;
        }

        $rows = NetWorthDaily::whereDate('date', $lastDate)->get();

        foreach ($rows as $row) {
            NetWorthMonthly::updateOrCreate(
                [
                    'month' => $month->copy()->startOfMonth()->toDateString(),
                    'character_id' => $row->character_id,
                ],
                [
                    'corporation_id' => $row->corporation_id,
                    'asset_value' => $row->asset_value,
                    'wallet_balance' => $row->wallet_balance,
                    'regions' => $row->regions,
                ]
            );
        }

        return $rows->count();
    }

    /**
     * One row per registered account, consolidating main + linked alts.
     *
     * @return object[] sorted by net worth, highest first
     */
    public function buildNetWorth(Carbon $month, bool $isCurrentMonth): array
    {
        $groups = $this->consolidation->allAccountGroups();

        if (empty($groups)) {
            return [];
        }

        $rows = $this->snapshotRowsFor($month, $isCurrentMonth);

        if ($rows->isEmpty()) {
            return [];
        }

        $previous = $this->previousMonthValues($month);

        $setting = MemberEngagementSetting::current();
        $homeRegions = array_map('strval', $setting->home_regions ?? []);
        $threshold = (int) ($setting->minimum_home_presence ?? 80);

        $byCharacter = $rows->keyBy('character_id');

        $accounts = collect($groups)->map(function ($characterIds, $mainId) use ($byCharacter, $previous, $homeRegions, $threshold) {
            $assetValue = 0.0;
            $walletBalance = 0.0;
            $regions = [];

            foreach ($characterIds as $characterId) {
                $row = $byCharacter[$characterId] ?? null;
                if (!$row) {
                    continue;
                }

                $assetValue += (float) $row->asset_value;
                $walletBalance += (float) $row->wallet_balance;

                foreach (($row->regions ?? []) as $regionId => $value) {
                    $regions[$regionId] = ($regions[$regionId] ?? 0) + (float) $value;
                }
            }

            // Unresolvable assets are left out of the denominator rather
            // than counted as "away" - they're not evidence of anything.
            $placed = array_sum(array_diff_key($regions, [self::UNKNOWN_REGION => 0]));
            $atHome = array_sum(array_intersect_key($regions, array_flip($homeRegions)));
            $homePresence = $placed > 0 ? ($atHome / $placed) * 100 : null;

            $netWorth = $assetValue + $walletBalance;
            $previousNetWorth = $previous[$mainId] ?? null;
            $changePercent = ($previousNetWorth !== null && $previousNetWorth > 0)
                ? (($netWorth - $previousNetWorth) / $previousNetWorth) * 100
                : null;

            return (object) [
                'character_id' => (int) $mainId,
                'corporation_id' => null,
                'metadata' => [],
                'member_count' => count($characterIds),
                'asset_value' => $assetValue,
                'wallet_balance' => $walletBalance,
                'net_worth' => $netWorth,
                'regions' => $regions,
                'value_at_home' => $atHome,
                'value_placed' => $placed,
                'value_elsewhere' => $placed - $atHome,
                'home_presence' => $homePresence,
                'below_threshold' => $homePresence !== null && $homePresence < $threshold,
                'change_percent' => $changePercent,
            ];
        })->filter(fn($account) => $account->net_worth > 0)->values();

        if ($accounts->isEmpty()) {
            return [];
        }

        $this->entityResolution->attachDisplayNames($accounts);

        return $accounts->sortByDesc('net_worth')->values()->all();
    }

    /**
     * The date the figures on screen were actually taken, so the view can
     * say how fresh they are.
     */
    public function snapshotTakenAt(Carbon $month, bool $isCurrentMonth): ?Carbon
    {
        if ($isCurrentMonth) {
            $latest = NetWorthDaily::max('date');

            return $latest ? Carbon::parse($latest) : null;
        }

        $end = $month->copy()->endOfMonth()->toDateString();
        $start = $month->copy()->startOfMonth()->toDateString();

        $latest = NetWorthDaily::whereBetween('date', [$start, $end])->max('date');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * Current month reads the newest daily snapshot; a past month reads
     * its finalized row, falling back to the last daily snapshot inside
     * that month if it was never finalized.
     */
    private function snapshotRowsFor(Carbon $month, bool $isCurrentMonth): Collection
    {
        if ($isCurrentMonth) {
            $latest = NetWorthDaily::max('date');

            return $latest ? NetWorthDaily::whereDate('date', $latest)->get() : collect();
        }

        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $finalized = NetWorthMonthly::whereDate('month', $monthStart)->get();

        if ($finalized->isNotEmpty()) {
            return $finalized;
        }

        $end = $month->copy()->endOfMonth()->toDateString();
        $latest = NetWorthDaily::whereBetween('date', [$monthStart, $end])->max('date');

        return $latest ? NetWorthDaily::whereDate('date', $latest)->get() : collect();
    }

    /**
     * Previous month's closing net worth per main character, for the
     * month-on-month change column.
     *
     * @return array<int, float>
     */
    private function previousMonthValues(Carbon $month): array
    {
        $previousMonth = $month->copy()->startOfMonth()->subMonthNoOverflow()->toDateString();

        $rows = NetWorthMonthly::whereDate('month', $previousMonth)->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $groupMap = $this->consolidation->resolveGroups($rows->pluck('character_id')->all());

        $totals = [];
        foreach ($rows as $row) {
            $mainId = $groupMap[$row->character_id] ?? null;
            if (!$mainId) {
                continue;
            }

            $totals[$mainId] = ($totals[$mainId] ?? 0) + (float) $row->asset_value + (float) $row->wallet_balance;
        }

        return $totals;
    }

    /**
     * Walks the asset containment tree to find where each asset really
     * sits, then prices it.
     *
     * Blueprint copies are excluded from the valuation (they price as
     * originals, which would wildly inflate the total) but stay in the
     * tree so anything inside them still resolves.
     *
     * Pricing falls back to the SDE's basePrice when an item has no market
     * price at all - supercarriers and the like can't be traded in Jita, so
     * they'd otherwise count as worthless. Ships additionally take the
     * HIGHER of market and basePrice, since thin markets understate
     * capitals. That floor is deliberately not applied everywhere:
     * basePrice is meaningless for manufactured goods (Scourge Rage
     * Torpedoes price at 1,502 ISK on the market against a basePrice of
     * 1,500,245), so a blanket GREATEST() would invent trillions.
     *
     * @param  int[]  $characterIds
     * @return array<int, array<string, float>> character_id => [region_id => value]
     */
    private function assetValuesByRegion(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $characterIds));
        $maxDepth = self::MAX_NESTING_DEPTH;
        $shipCategory = self::SHIP_CATEGORY_ID;

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, a.location_id AS root_location, 0 AS depth
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, r.root_location, r.depth + 1
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
            )
            SELECT r.character_id,
                   COALESCE(md_direct.regionID, md_struct.regionID) AS region_id,
                   SUM(
                       CASE WHEN r.is_blueprint_copy = 1 THEN 0
                            ELSE r.quantity * CASE
                                WHEN ic.categoryID = {$shipCategory}
                                    THEN GREATEST(COALESCE(mp.average_price, 0), COALESCE(it.basePrice, 0))
                                WHEN COALESCE(mp.average_price, 0) > 0
                                    THEN mp.average_price
                                ELSE COALESCE(it.basePrice, 0)
                            END
                       END
                   ) AS value
            FROM roots r
            LEFT JOIN universe_structures us ON us.structure_id = r.root_location
            LEFT JOIN mapDenormalize md_direct ON md_direct.itemID = r.root_location
            LEFT JOIN mapDenormalize md_struct ON md_struct.itemID = us.solar_system_id
            LEFT JOIN market_prices mp ON mp.type_id = r.type_id
            LEFT JOIN invTypes it ON it.typeID = r.type_id
            LEFT JOIN invGroups ig ON ig.groupID = it.groupID
            LEFT JOIN invCategories ic ON ic.categoryID = ig.categoryID
            GROUP BY r.character_id, region_id
        SQL;

        $byCharacter = [];
        foreach (DB::select($sql) as $row) {
            $value = (float) $row->value;
            if ($value <= 0) {
                continue;
            }

            $regionKey = $row->region_id === null ? self::UNKNOWN_REGION : (string) $row->region_id;
            $byCharacter[(int) $row->character_id][$regionKey] = $value;
        }

        return $byCharacter;
    }
}

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

    /**
     * invGroups.groupID for PLEX. It sits in the account-wide vault, which
     * has no station/structure/region, so it is counted as currency
     * alongside the wallet rather than as an asset in an unknown place.
     */
    private const PLEX_GROUP_ID = 1875;

    /** invCategories.categoryID for ships. */
    private const SHIP_CATEGORY_ID = 6;

    /**
     * Slices shown individually on the region pie before the tail is folded
     * into "Other". Three is what the categorical palette is documented to
     * guarantee when every pair can be compared against every other, which
     * is how a pie is read. Asset value is concentrated enough (one home
     * region typically holds 90%+) that the tail is tiny anyway.
     */
    private const PIE_SLICES = 3;

    /** Categorical slots 1-3, plus a neutral for the folded tail. */
    private const SLICE_COLOURS = ['#2a78d6', '#eb6834', '#1baf7a'];
    private const OTHER_COLOUR = '#8a8a85';
    private const UNKNOWN_COLOUR = '#c9c8c0';

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
        $plexByCharacter = $this->plexValues($characterIds->all());

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
                    'plex_value' => (float) ($plexByCharacter[$characterId] ?? 0),
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
                    'plex_value' => $row->plex_value,
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
            $plexValue = 0.0;
            $regions = [];

            foreach ($characterIds as $characterId) {
                $row = $byCharacter[$characterId] ?? null;
                if (!$row) {
                    continue;
                }

                $assetValue += (float) $row->asset_value;
                $walletBalance += (float) $row->wallet_balance;
                $plexValue += (float) $row->plex_value;

                foreach (($row->regions ?? []) as $regionId => $value) {
                    $regions[$regionId] = ($regions[$regionId] ?? 0) + (float) $value;
                }
            }

            // Unresolvable assets are left out of the denominator rather
            // than counted as "away" - they're not evidence of anything.
            $placed = array_sum(array_diff_key($regions, [self::UNKNOWN_REGION => 0]));
            $atHome = array_sum(array_intersect_key($regions, array_flip($homeRegions)));
            $homePresence = $placed > 0 ? ($atHome / $placed) * 100 : null;

            $netWorth = $assetValue + $walletBalance + $plexValue;
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
                'plex_value' => $plexValue,
                'currency_value' => $walletBalance + $plexValue,
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
        $this->attachRegionBreakdown($accounts);

        return $accounts->sortByDesc('net_worth')->values()->all();
    }

    /**
     * Turns each account's raw region map into something presentable: every
     * region named and ranked, plus the handful of slices the pie draws.
     * Percentages are of everything the account holds, unresolved included,
     * so the slices total 100%.
     */
    private function attachRegionBreakdown(Collection $accounts): void
    {
        $regionIds = $accounts
            ->flatMap(fn($account) => array_keys($account->regions))
            ->reject(fn($id) => $id === self::UNKNOWN_REGION)
            ->unique();

        $names = $regionIds->isEmpty()
            ? collect()
            : DB::table('regions')->whereIn('region_id', $regionIds)->pluck('name', 'region_id');

        foreach ($accounts as $account) {
            $unresolved = (float) ($account->regions[self::UNKNOWN_REGION] ?? 0);
            $total = $account->value_placed + $unresolved;

            $breakdown = collect($account->regions)
                ->except([self::UNKNOWN_REGION])
                ->map(fn($value, $regionId) => [
                    'region_id' => $regionId,
                    'name' => $names[$regionId] ?? "Region {$regionId}",
                    'value' => (float) $value,
                    'percent' => $total > 0 ? ((float) $value / $total) * 100 : 0,
                ])
                ->sortByDesc('value')
                ->values();

            $account->region_breakdown = $breakdown->all();

            $slices = $breakdown->take(self::PIE_SLICES)->values()
                ->map(fn($slice, $index) => $slice + ['colour' => self::SLICE_COLOURS[$index]])
                ->all();

            $tail = $breakdown->slice(self::PIE_SLICES);
            if ($tail->isNotEmpty()) {
                $slices[] = [
                    'region_id' => 'other',
                    'name' => 'Other (' . $tail->count() . ' regions)',
                    'value' => (float) $tail->sum('value'),
                    'percent' => (float) $tail->sum('percent'),
                    'colour' => self::OTHER_COLOUR,
                ];
            }

            // Shown last, and in its own neutral rather than a categorical
            // hue - it isn't a place, it's the bit we couldn't place. The
            // home presence figure still ignores it, which is why the chart
            // caption spells the difference out.
            if ($unresolved > 0) {
                $slices[] = [
                    'region_id' => self::UNKNOWN_REGION,
                    'name' => 'Unknown location',
                    'value' => $unresolved,
                    'percent' => $total > 0 ? ($unresolved / $total) * 100 : 0,
                    'colour' => self::UNKNOWN_COLOUR,
                ];
            }

            $account->chart_slices = $slices;
            $account->unresolved_value = $unresolved;
        }
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

            $totals[$mainId] = ($totals[$mainId] ?? 0)
                + (float) $row->asset_value
                + (float) $row->wallet_balance
                + (float) $row->plex_value;
        }

        return $totals;
    }

    /**
     * PLEX type IDs as a SQL list, for excluding them from the asset tree.
     * Falls back to a value that matches nothing if the SDE has no PLEX
     * group, so the NOT IN stays valid SQL.
     */
    private function plexTypeList(): string
    {
        $types = DB::table('invTypes')
            ->where('groupID', self::PLEX_GROUP_ID)
            ->pluck('typeID')
            ->map(fn($id) => (int) $id);

        return $types->isEmpty() ? '0' : $types->implode(',');
    }

    /**
     * PLEX held per character, valued at market.
     *
     * @param  int[]  $characterIds
     * @return array<int, float>
     */
    private function plexValues(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        return DB::table('character_assets')
            ->join('invTypes', 'invTypes.typeID', '=', 'character_assets.type_id')
            ->leftJoin('market_prices', 'market_prices.type_id', '=', 'character_assets.type_id')
            ->whereIn('character_assets.character_id', $characterIds)
            ->where('invTypes.groupID', self::PLEX_GROUP_ID)
            ->groupBy('character_assets.character_id')
            ->selectRaw('character_assets.character_id, SUM(character_assets.quantity * COALESCE(market_prices.average_price, 0)) AS value')
            ->pluck('value', 'character_id')
            ->map(fn($value) => (float) $value)
            ->all();
    }

    /**
     * How an asset is priced, as SQL. Shared so the per-region totals and
     * the unresolved-asset listing can never drift apart.
     */
    private function priceExpression(string $alias = 'r'): string
    {
        $shipCategory = self::SHIP_CATEGORY_ID;

        return "CASE WHEN {$alias}.is_blueprint_copy = 1 THEN 0
                     ELSE {$alias}.quantity * CASE
                         WHEN ic.categoryID = {$shipCategory}
                             THEN GREATEST(COALESCE(mp.average_price, 0), COALESCE(it.basePrice, 0))
                         WHEN COALESCE(mp.average_price, 0) > 0
                             THEN mp.average_price
                         ELSE COALESCE(it.basePrice, 0)
                     END
                END";
    }

    /**
     * The individual assets behind an account's "unknown location" slice,
     * grouped by the container SeAT couldn't place and nested the way they
     * actually sit inside it.
     *
     * @param  int[]  $characterIds
     * @return array<int, array{location_id: string, total_value: float, items: array}>
     */
    public function unresolvedAssets(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $characterIds));
        $maxDepth = self::MAX_NESTING_DEPTH;
        $priceExpression = $this->priceExpression();
        $plexTypes = $this->plexTypeList();

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, a.location_id, a.location_flag,
                       a.location_id AS root_location, 0 AS depth
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND a.type_id NOT IN ({$plexTypes})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, a.location_id, a.location_flag,
                       r.root_location, r.depth + 1
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
                  AND a.type_id NOT IN ({$plexTypes})
            )
            SELECT r.item_id, r.location_id, r.root_location, r.quantity,
                   COALESCE(it.typeName, CONCAT('Type ', r.type_id)) AS type_name,
                   {$priceExpression} AS value
            FROM roots r
            LEFT JOIN universe_structures us ON us.structure_id = r.root_location
            LEFT JOIN mapDenormalize md_direct ON md_direct.itemID = r.root_location
            LEFT JOIN mapDenormalize md_struct ON md_struct.itemID = us.solar_system_id
            LEFT JOIN market_prices mp ON mp.type_id = r.type_id
            LEFT JOIN invTypes it ON it.typeID = r.type_id
            LEFT JOIN invGroups ig ON ig.groupID = it.groupID
            LEFT JOIN invCategories ic ON ic.categoryID = ig.categoryID
            WHERE COALESCE(md_direct.regionID, md_struct.regionID) IS NULL
            ORDER BY type_name
        SQL;

        $rows = collect(DB::select($sql));

        if ($rows->isEmpty()) {
            return [];
        }

        $childrenByParent = $rows->groupBy(fn($row) => (string) $row->location_id);

        $build = function (string $parentId) use (&$build, $childrenByParent): array {
            return ($childrenByParent[$parentId] ?? collect())
                ->map(fn($row) => [
                    'name' => $row->type_name,
                    'quantity' => (int) $row->quantity,
                    'value' => (float) $row->value,
                    'children' => $build((string) $row->item_id),
                ])
                ->values()
                ->all();
        };

        return $rows->groupBy(fn($row) => (string) $row->root_location)
            ->map(fn($group, $location) => [
                'location_id' => (string) $location,
                'total_value' => (float) $group->sum('value'),
                'items' => $build((string) $location),
            ])
            ->sortByDesc('total_value')
            ->values()
            ->all();
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
        $priceExpression = $this->priceExpression();
        $plexTypes = $this->plexTypeList();

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, a.location_id AS root_location, 0 AS depth
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND a.type_id NOT IN ({$plexTypes})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, r.root_location, r.depth + 1
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
                  AND a.type_id NOT IN ({$plexTypes})
            )
            SELECT r.character_id,
                   COALESCE(md_direct.regionID, md_struct.regionID) AS region_id,
                   SUM(
                       {$priceExpression}
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

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

    /** Pulled out of their region: in limbo after a structure died. */
    public const ASSET_SAFETY = 'asset_safety';

    /**
     * An unresolvable location holding nothing but planetary commodities
     * is, in practice, a customs office - they sit at a planet rather than
     * a station or structure, so they never resolve.
     */
    public const CUSTOMS_OFFICE = 'customs_office';

    /** invTypes.typeID of the Asset Safety Wrap container. */
    private const ASSET_SAFETY_WRAP_TYPE_ID = 60;

    /** invCategories.categoryID for Planetary Commodities. */
    private const PI_CATEGORY_ID = 43;

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
    private const ASSET_SAFETY_COLOUR = '#e34948';
    private const CUSTOMS_OFFICE_COLOUR = '#4a3aa7';

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
        $deploymentRegions = array_map('strval', $setting->deployment_regions ?? []);
        $threshold = (int) ($setting->minimum_home_presence ?? 80);

        $byCharacter = $rows->keyBy('character_id');

        $accounts = collect($groups)->map(function ($characterIds, $mainId) use ($byCharacter, $previous, $homeRegions, $deploymentRegions, $threshold) {
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

            // Assets we can't place are left out of the denominator rather
            // than counted as "away" - they're not evidence of anything.
            // Asset safety stays in: we know exactly where it is, and it
            // isn't usable at home, so it should count against presence.
            $placed = array_sum(array_diff_key($regions, [
                self::UNKNOWN_REGION => 0,
                self::CUSTOMS_OFFICE => 0,
            ]));
            $atHome = array_sum(array_intersect_key($regions, array_flip($homeRegions)));
            $deployed = array_sum(array_intersect_key($regions, array_flip($deploymentRegions)));

            // Deployed counts the same as home: a member who moved their
            // ships to the deployment is where they're supposed to be. The
            // minimum is measured against the two together.
            $percent = fn(float $value) => $placed > 0 ? ($value / $placed) * 100 : null;
            $homePresence = $percent($atHome);
            $deployedPresence = $percent($deployed);
            $combinedPresence = $percent($atHome + $deployed);

            // PLEX is a separate vault, not the wallet and not a hangar
            // asset, so it is tracked but deliberately left out of net
            // worth.
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
                'plex_value' => $plexValue,
                'net_worth' => $netWorth,
                'regions' => $regions,
                'value_at_home' => $atHome,
                'value_deployed' => $deployed,
                'value_placed' => $placed,
                'value_elsewhere' => $placed - $atHome - $deployed,
                'home_presence' => $homePresence,
                'deployed_presence' => $deployedPresence,
                'combined_presence' => $combinedPresence,
                'below_threshold' => $combinedPresence !== null && $combinedPresence < $threshold,
                'change_percent' => $changePercent,
            ];
        })->filter(fn($account) => $account->net_worth > 0)->values();

        if ($accounts->isEmpty()) {
            return [];
        }

        $this->entityResolution->attachDisplayNames($accounts);
        $this->attachRegionBreakdown($accounts, array_merge($homeRegions, $deploymentRegions));

        return $accounts->sortByDesc('net_worth')->values()->all();
    }

    /**
     * Turns each account's raw region map into something presentable: every
     * region named and ranked, plus the handful of slices the pie draws.
     * Percentages are of everything the account holds, unresolved included,
     * so the slices total 100%.
     */
    private function attachRegionBreakdown(Collection $accounts, array $settledRegions = []): void
    {
        $settled = array_flip(array_map('strval', $settledRegions));

        $regionIds = $accounts
            ->flatMap(fn($account) => array_keys($account->regions))
            ->reject(fn($id) => $id === self::UNKNOWN_REGION)
            ->unique();

        $names = $regionIds->isEmpty()
            ? collect()
            : DB::table('regions')->whereIn('region_id', $regionIds)->pluck('name', 'region_id');

        $specials = [
            self::ASSET_SAFETY => ['label' => 'Asset Safety', 'colour' => self::ASSET_SAFETY_COLOUR],
            self::CUSTOMS_OFFICE => ['label' => 'Customs Offices', 'colour' => self::CUSTOMS_OFFICE_COLOUR],
            self::UNKNOWN_REGION => ['label' => 'Unknown location', 'colour' => self::UNKNOWN_COLOUR],
        ];

        foreach ($accounts as $account) {
            $unresolved = (float) ($account->regions[self::UNKNOWN_REGION] ?? 0);
            $total = array_sum($account->regions);

            $breakdown = collect($account->regions)
                ->except(array_keys($specials))
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
                ->map(fn($slice, $index) => $slice + [
                    'colour' => self::SLICE_COLOURS[$index],
                    // Home and deployment regions are where the assets are
                    // meant to be, so there's nothing to investigate.
                    'drillable' => !isset($settled[(string) $slice['region_id']]),
                ])
                ->all();

            $tail = $breakdown->slice(self::PIE_SLICES);
            if ($tail->isNotEmpty()) {
                $slices[] = [
                    'region_id' => 'other',
                    'name' => 'Other (' . $tail->count() . ' regions)',
                    'value' => (float) $tail->sum('value'),
                    'percent' => (float) $tail->sum('percent'),
                    'colour' => self::OTHER_COLOUR,
                    'drillable' => true,
                ];
            }

            // Shown last and in their own neutrals rather than categorical
            // hues - none of them is an ordinary region.
            foreach ($specials as $key => $special) {
                $value = (float) ($account->regions[$key] ?? 0);
                if ($value <= 0) {
                    continue;
                }

                $slices[] = [
                    'region_id' => $key,
                    'name' => $special['label'],
                    'value' => $value,
                    'percent' => $total > 0 ? ($value / $total) * 100 : 0,
                    'colour' => $special['colour'],
                    'drillable' => true,
                ];
            }

            $account->chart_slices = $slices;
            $account->unresolved_value = $unresolved;
            $account->customs_office_value = (float) ($account->regions[self::CUSTOMS_OFFICE] ?? 0);
            $account->asset_safety_value = (float) ($account->regions[self::ASSET_SAFETY] ?? 0);
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
                + (float) $row->wallet_balance;
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
    public function bucketAssets(array $characterIds, string $bucket): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $characterIds));
        $maxDepth = self::MAX_NESTING_DEPTH;
        $priceExpression = $this->priceExpression();
        $plexTypes = $this->plexTypeList();
        $wrapType = self::ASSET_SAFETY_WRAP_TYPE_ID;

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity, a.name AS asset_name,
                       a.is_blueprint_copy, a.location_id, a.location_id AS root_location, 0 AS depth,
                       CASE WHEN a.type_id = {$wrapType} OR a.location_flag = 'AssetSafety'
                            THEN 1 ELSE 0 END AS in_asset_safety
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND a.type_id NOT IN ({$plexTypes})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity, a.name,
                       a.is_blueprint_copy, a.location_id, r.root_location, r.depth + 1,
                       GREATEST(r.in_asset_safety,
                                CASE WHEN a.type_id = {$wrapType} OR a.location_flag = 'AssetSafety'
                                     THEN 1 ELSE 0 END)
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
                  AND a.type_id NOT IN ({$plexTypes})
            )
            SELECT r.item_id, r.location_id, r.root_location, r.quantity, r.type_id,
                   r.asset_name, r.in_asset_safety,
                   COALESCE(md_direct.regionID, md_struct.regionID) AS region_id,
                   COALESCE(it.typeName, CONCAT('Type ', r.type_id)) AS type_name,
                   ic.categoryID AS category_id,
                   {$priceExpression} AS value
            FROM roots r
            LEFT JOIN universe_structures us ON us.structure_id = r.root_location
            LEFT JOIN mapDenormalize md_direct ON md_direct.itemID = r.root_location
            LEFT JOIN mapDenormalize md_struct ON md_struct.itemID = us.solar_system_id
            LEFT JOIN market_prices mp ON mp.type_id = r.type_id
            LEFT JOIN invTypes it ON it.typeID = r.type_id
            LEFT JOIN invGroups ig ON ig.groupID = it.groupID
            LEFT JOIN invCategories ic ON ic.categoryID = ig.categoryID
            ORDER BY type_name
        SQL;

        $rows = collect(DB::select($sql));

        if (ctype_digit($bucket)) {
            // A single region: everything resolved there that isn't in
            // asset safety, which is reported on its own.
            $wanted = $rows->filter(fn($row) => (int) $row->in_asset_safety === 0
                && (string) $row->region_id === $bucket);
        } elseif ($bucket === self::ASSET_SAFETY) {
            $wanted = $rows->filter(fn($row) => (int) $row->in_asset_safety === 1);
        } else {
            // Customs offices are the unplaceable locations holding nothing
            // but planetary commodities; the rest are simply unknown.
            $unplaceable = $rows->filter(fn($row) => $row->region_id === null && (int) $row->in_asset_safety === 0);
            $piOnly = $unplaceable->groupBy('root_location')
                ->filter(fn($group) => $group->every(fn($row) => (int) $row->category_id === self::PI_CATEGORY_ID))
                ->keys()
                ->map(fn($key) => (string) $key)
                ->all();

            $wanted = $unplaceable->filter(function ($row) use ($bucket, $piOnly) {
                $isCustoms = in_array((string) $row->root_location, $piOnly, true);

                return $bucket === self::CUSTOMS_OFFICE ? $isCustoms : !$isCustoms;
            });
        }

        if ($wanted->isEmpty()) {
            return [];
        }

        // Nest against every row, not just the wanted ones, so a ship in
        // asset safety still shows the modules inside it.
        $childrenByParent = $rows->groupBy(fn($row) => (string) $row->location_id);
        $wantedIds = $wanted->pluck('item_id')->map(fn($id) => (string) $id)->flip();

        $build = function (string $parentId) use (&$build, $childrenByParent): array {
            return ($childrenByParent[$parentId] ?? collect())
                ->map(fn($row) => [
                    'type_id' => (int) $row->type_id,
                    'name' => $row->type_name,
                    'given_name' => $row->asset_name ?: null,
                    'quantity' => (int) $row->quantity,
                    'value' => (float) $row->value,
                    'children' => $build((string) $row->item_id),
                ])
                ->values()
                ->all();
        };

        // Only the topmost wanted rows start a tree; the rest hang off them.
        $tops = $wanted->filter(fn($row) => !$wantedIds->has((string) $row->location_id));

        $names = $this->locationNames($tops->pluck('root_location')->unique()->all(), $bucket);

        return $tops->groupBy(fn($row) => (string) $row->root_location)
            ->map(fn($group, $location) => [
                'location_id' => (string) $location,
                'location_name' => $names[(string) $location]['name'],
                'location_type_id' => $names[(string) $location]['type_id'],
                'total_value' => (float) $group->sum(fn($row) => $row->value + $this->descendantValue($childrenByParent, (string) $row->item_id)),
                'items' => $group->map(fn($row) => [
                    'type_id' => (int) $row->type_id,
                    'name' => $row->type_name,
                    'given_name' => $row->asset_name ?: null,
                    'quantity' => (int) $row->quantity,
                    'value' => (float) $row->value,
                    'children' => $build((string) $row->item_id),
                ])->values()->all(),
            ])
            ->sortByDesc('total_value')
            ->values()
            ->all();
    }

    /**
     * Names for the containers a drill-down groups by. Everything needed
     * is already local: player structures SeAT has resolved, and the SDE
     * for NPC stations. mapDenormalize covers most of the latter, but not
     * all of them - Zarzakh's station is in staStations only - so both are
     * consulted.
     *
     * What is left over is genuinely unreachable: customs offices, the
     * PLEX vault, and structures nobody linked here has docking access to.
     * ESI can't name those either, so they fall back to a label rather
     * than a bare id.
     *
     * @param  array  $locationIds
     * @return array<string, array{name: string, type_id: int|null}>
     */
    private function locationNames(array $locationIds, string $bucket): array
    {
        $ids = collect($locationIds)->map(fn($id) => (string) $id)->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        $structures = DB::table('universe_structures')
            ->whereIn('structure_id', $ids)
            ->get(['structure_id', 'name', 'type_id'])
            ->keyBy(fn($row) => (string) $row->structure_id);

        $sde = DB::table('mapDenormalize')
            ->whereIn('itemID', $ids)
            ->whereNotNull('itemName')
            ->get(['itemID', 'itemName', 'typeID'])
            ->keyBy(fn($row) => (string) $row->itemID);

        $stations = DB::table('staStations')
            ->whereIn('stationID', $ids)
            ->get(['stationID', 'stationName', 'stationTypeID'])
            ->keyBy(fn($row) => (string) $row->stationID);

        $fallback = $bucket === self::CUSTOMS_OFFICE ? 'Customs office' : 'Unreachable structure';

        return $ids->mapWithKeys(function ($id) use ($structures, $sde, $stations, $fallback) {
            if ($structure = $structures->get($id)) {
                return [$id => ['name' => $structure->name, 'type_id' => (int) $structure->type_id]];
            }
            if ($entry = $sde->get($id)) {
                return [$id => ['name' => $entry->itemName, 'type_id' => (int) $entry->typeID]];
            }
            if ($station = $stations->get($id)) {
                return [$id => ['name' => $station->stationName, 'type_id' => (int) $station->stationTypeID]];
            }

            return [$id => ['name' => $fallback . ' ' . $id, 'type_id' => null]];
        })->all();
    }

    /** Value of everything nested below an item, at any depth. */
    private function descendantValue($childrenByParent, string $itemId): float
    {
        return ($childrenByParent[$itemId] ?? collect())
            ->sum(fn($row) => (float) $row->value + $this->descendantValue($childrenByParent, (string) $row->item_id));
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

        $rows = $this->assetBuckets($characterIds);

        // An unresolvable location counts as a customs office only if
        // everything in it is planetary commodities - a structure SeAT
        // can't see would hold a mixture.
        $unplaceable = [];
        foreach ($rows as $row) {
            if ($row->region_id !== null || $row->in_asset_safety) {
                continue;
            }

            $key = $row->character_id . '|' . $row->root_location;
            $unplaceable[$key]['pi'] = ($unplaceable[$key]['pi'] ?? 0) + (float) $row->pi_value;
            $unplaceable[$key]['other'] = ($unplaceable[$key]['other'] ?? 0) + (float) $row->other_value;
        }

        $byCharacter = [];
        foreach ($rows as $row) {
            $value = (float) $row->pi_value + (float) $row->other_value;
            if ($value <= 0) {
                continue;
            }

            if ($row->in_asset_safety) {
                $bucket = self::ASSET_SAFETY;
            } elseif ($row->region_id !== null) {
                $bucket = (string) $row->region_id;
            } else {
                $totals = $unplaceable[$row->character_id . '|' . $row->root_location] ?? ['pi' => 0, 'other' => 0];
                $bucket = ($totals['other'] <= 0 && $totals['pi'] > 0)
                    ? self::CUSTOMS_OFFICE
                    : self::UNKNOWN_REGION;
            }

            $characterId = (int) $row->character_id;
            $byCharacter[$characterId][$bucket] = ($byCharacter[$characterId][$bucket] ?? 0) + $value;
        }

        return $byCharacter;
    }

    /**
     * Every asset resolved to where it sits, split by whether it is
     * planetary commodities, so the caller can tell a customs office from
     * a structure SeAT simply can't see.
     *
     * @param  int[]  $characterIds
     */
    private function assetBuckets(array $characterIds): array
    {
        $ids = implode(',', array_map('intval', $characterIds));
        $maxDepth = self::MAX_NESTING_DEPTH;
        $priceExpression = $this->priceExpression();
        $plexTypes = $this->plexTypeList();
        $wrapType = self::ASSET_SAFETY_WRAP_TYPE_ID;
        $piCategory = self::PI_CATEGORY_ID;

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, a.location_id AS root_location, 0 AS depth,
                       CASE WHEN a.type_id = {$wrapType} OR a.location_flag = 'AssetSafety'
                            THEN 1 ELSE 0 END AS in_asset_safety
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND a.type_id NOT IN ({$plexTypes})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.is_blueprint_copy, r.root_location, r.depth + 1,
                       GREATEST(r.in_asset_safety,
                                CASE WHEN a.type_id = {$wrapType} OR a.location_flag = 'AssetSafety'
                                     THEN 1 ELSE 0 END)
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
                  AND a.type_id NOT IN ({$plexTypes})
            )
            SELECT r.character_id,
                   r.root_location,
                   r.in_asset_safety,
                   COALESCE(md_direct.regionID, md_struct.regionID) AS region_id,
                   SUM(CASE WHEN ic.categoryID = {$piCategory} THEN {$priceExpression} ELSE 0 END) AS pi_value,
                   SUM(CASE WHEN ic.categoryID = {$piCategory} THEN 0 ELSE {$priceExpression} END) AS other_value
            FROM roots r
            LEFT JOIN universe_structures us ON us.structure_id = r.root_location
            LEFT JOIN mapDenormalize md_direct ON md_direct.itemID = r.root_location
            LEFT JOIN mapDenormalize md_struct ON md_struct.itemID = us.solar_system_id
            LEFT JOIN market_prices mp ON mp.type_id = r.type_id
            LEFT JOIN invTypes it ON it.typeID = r.type_id
            LEFT JOIN invGroups ig ON ig.groupID = it.groupID
            LEFT JOIN invCategories ic ON ic.categoryID = ig.categoryID
            GROUP BY r.character_id, r.root_location, r.in_asset_safety, region_id
        SQL;

        return DB::select($sql);
    }
}

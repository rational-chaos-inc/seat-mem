<?php

namespace RCI\MemberEngagement\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds every capital hull held by the accounts this install tracks, and
 * where each one is parked.
 *
 * Hulls are normally sat in a hangar, but a capital can also be inside
 * another ship's maintenance bay, so the containment tree is walked the
 * same way the net worth figures do it rather than trusting location_id
 * to already be a station.
 */
class CapitalTrackingService
{
    /**
     * invGroups.groupID for the hull classes worth tracking, in the order
     * they are shown. Freighters aren't combat capitals but they're
     * capital-sized logistics and directors ask after them, so they're
     * here too - at the end.
     */
    private const CAPITAL_GROUPS = [
        30 => 'Titan',
        659 => 'Supercarrier',
        485 => 'Dreadnought',
        4594 => 'Lancer Dreadnought',
        547 => 'Carrier',
        1538 => 'Force Auxiliary',
        883 => 'Capital Industrial Ship',
        902 => 'Jump Freighter',
        513 => 'Freighter',
    ];

    /** Guards against a cycle in the containment tree. */
    private const MAX_NESTING_DEPTH = 20;

    public function __construct(
        private AccountConsolidationService $consolidation,
        private EntityResolutionService $entityResolution,
    ) {
    }

    /** @return string[] groupID => name, in display order */
    public function groups(): array
    {
        return self::CAPITAL_GROUPS;
    }

    /**
     * @return array{types: array, accounts: array}
     */
    public function build(): array
    {
        $groups = $this->consolidation->allAccountGroups();

        if (empty($groups)) {
            return ['types' => [], 'accounts' => []];
        }

        $characterToMain = [];
        foreach ($groups as $mainId => $characterIds) {
            foreach ($characterIds as $characterId) {
                $characterToMain[$characterId] = $mainId;
            }
        }

        $hulls = $this->hulls(array_keys($characterToMain));

        if ($hulls->isEmpty()) {
            return ['types' => [], 'accounts' => []];
        }

        $names = $this->characterNames($hulls->pluck('character_id')->unique()->all());
        $accountNames = $this->accountNames(array_keys($groups));

        $hulls = $hulls->map(fn($hull) => (object) [
            'main_character_id' => $characterToMain[$hull->character_id],
            'character_id' => (int) $hull->character_id,
            'character_name' => $names[$hull->character_id] ?? "Unknown ({$hull->character_id})",
            'type_id' => (int) $hull->type_id,
            'type_name' => $hull->type_name,
            'group_id' => (int) $hull->group_id,
            'group_name' => self::CAPITAL_GROUPS[(int) $hull->group_id] ?? $hull->group_name,
            'system' => $hull->system_name ?? 'Unknown location',
            'region' => $hull->region_name ?? 'Unknown',
            'quantity' => (int) $hull->quantity,
        ]);

        return [
            'types' => $this->byType($hulls, $accountNames),
            'accounts' => $this->byAccount($hulls, $groups, $accountNames),
        ];
    }

    /**
     * One row per hull type, with the accounts holding it.
     */
    private function byType(Collection $hulls, array $accountNames): array
    {
        return $hulls
            ->groupBy('type_id')
            ->map(function (Collection $ofType) use ($accountNames) {
                $first = $ofType->first();

                $holders = $ofType
                    ->groupBy('main_character_id')
                    ->map(fn(Collection $theirs, $mainId) => [
                        'main_character_id' => (int) $mainId,
                        'name' => $accountNames[(int) $mainId] ?? "Unknown ({$mainId})",
                        'count' => (int) $theirs->sum('quantity'),
                    ])
                    ->sortByDesc('count')
                    ->values()
                    ->all();

                return [
                    'type_id' => $first->type_id,
                    'type_name' => $first->type_name,
                    'group_id' => $first->group_id,
                    'group_name' => $first->group_name,
                    'total' => (int) $ofType->sum('quantity'),
                    'holders' => $holders,
                ];
            })
            // Heaviest class first, then most numerous within it.
            ->sortBy([
                fn($a, $b) => $this->groupOrder($a['group_id']) <=> $this->groupOrder($b['group_id']),
                fn($a, $b) => $b['total'] <=> $a['total'],
            ])
            ->values()
            ->all();
    }

    /**
     * One row per account, with a count per hull class and the individual
     * ships behind it.
     *
     * @param  array<int, int[]>  $groups
     */
    private function byAccount(Collection $hulls, array $groups, array $accountNames): array
    {
        $byAccount = $hulls->groupBy('main_character_id');

        return collect($groups)
            ->map(function (array $characterIds, $mainId) use ($byAccount, $accountNames) {
                $theirs = $byAccount->get($mainId) ?? collect();

                $counts = [];
                foreach (array_keys(self::CAPITAL_GROUPS) as $groupId) {
                    $counts[$groupId] = (int) $theirs->where('group_id', $groupId)->sum('quantity');
                }

                return [
                    'main_character_id' => (int) $mainId,
                    'name' => $accountNames[(int) $mainId] ?? "Unknown ({$mainId})",
                    'member_count' => count($characterIds),
                    'counts' => $counts,
                    'total' => (int) $theirs->sum('quantity'),
                    'ships' => $theirs
                        ->sortBy([
                            fn($a, $b) => $this->groupOrder($a->group_id) <=> $this->groupOrder($b->group_id),
                            fn($a, $b) => strcmp($a->type_name, $b->type_name),
                        ])
                        ->map(fn($hull) => [
                            'type_id' => $hull->type_id,
                            'type_name' => $hull->type_name,
                            'group_name' => $hull->group_name,
                            'character_name' => $hull->character_name,
                            'system' => $hull->system,
                            'region' => $hull->region,
                            'quantity' => $hull->quantity,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Position in the display order; unknown classes sort last. */
    private function groupOrder(int $groupId): int
    {
        $position = array_search($groupId, array_keys(self::CAPITAL_GROUPS), true);

        return $position === false ? count(self::CAPITAL_GROUPS) : $position;
    }

    /**
     * Every capital hull held by the given characters, resolved to the
     * system and region it is sitting in.
     *
     * @param  int[]  $characterIds
     */
    private function hulls(array $characterIds): Collection
    {
        if (empty($characterIds)) {
            return collect();
        }

        $ids = implode(',', array_map('intval', $characterIds));
        $groupIds = implode(',', array_keys(self::CAPITAL_GROUPS));
        $maxDepth = self::MAX_NESTING_DEPTH;

        $sql = <<<SQL
            WITH RECURSIVE roots AS (
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       a.location_id AS root_location, 0 AS depth
                FROM character_assets a
                WHERE a.character_id IN ({$ids})
                  AND NOT EXISTS (
                      SELECT 1 FROM character_assets p WHERE p.item_id = a.location_id
                  )
                UNION ALL
                SELECT a.item_id, a.character_id, a.type_id, a.quantity,
                       r.root_location, r.depth + 1
                FROM character_assets a
                JOIN roots r ON a.location_id = r.item_id
                WHERE r.depth < {$maxDepth}
            )
            SELECT r.character_id, r.type_id, r.quantity,
                   it.typeName AS type_name,
                   ig.groupID AS group_id,
                   ig.groupName AS group_name,
                   ss.name AS system_name,
                   reg.name AS region_name
            FROM roots r
            JOIN invTypes it ON it.typeID = r.type_id
            JOIN invGroups ig ON ig.groupID = it.groupID
            LEFT JOIN universe_structures us ON us.structure_id = r.root_location
            LEFT JOIN mapDenormalize md ON md.itemID = r.root_location
            LEFT JOIN staStations sta ON sta.stationID = r.root_location
            LEFT JOIN solar_systems ss
                   ON ss.system_id = COALESCE(us.solar_system_id, md.solarSystemID, sta.solarSystemID)
            LEFT JOIN regions reg ON reg.region_id = ss.region_id
            WHERE ig.groupID IN ({$groupIds})
        SQL;

        return collect(DB::select($sql));
    }

    /**
     * @param  int[]  $characterIds
     * @return array<int, string>
     */
    private function characterNames(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $names = DB::table('character_infos')
            ->whereIn('character_id', $characterIds)
            ->pluck('name', 'character_id');

        $missing = array_diff($characterIds, $names->keys()->all());

        if (!empty($missing)) {
            $names = $names->merge(
                DB::table('member_engagement_entity_names')
                    ->whereIn('entity_id', $missing)
                    ->pluck('name', 'entity_id')
            );
        }

        return $names->mapWithKeys(fn($name, $id) => [(int) $id => $name])->all();
    }

    /**
     * Main characters with the full "Character (Corp) [Alliance]" label the
     * rest of the module uses.
     *
     * @param  int[]  $mainCharacterIds
     * @return array<int, string>
     */
    private function accountNames(array $mainCharacterIds): array
    {
        $rows = collect($mainCharacterIds)->map(fn($id) => (object) [
            'character_id' => (int) $id,
            'corporation_id' => null,
            'metadata' => [],
        ]);

        $this->entityResolution->attachDisplayNames($rows);

        return $rows->mapWithKeys(fn($row) => [$row->character_id => $row->display_name])->all();
    }
}

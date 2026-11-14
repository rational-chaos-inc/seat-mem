<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
     *
     * A class normally only counts if the install's SDE knows about it,
     * and SeAT pins the SDE to one build in its own manifest, so a class
     * CCP has added recently is not resolvable at all. FALLBACK_HULLS
     * covers the affected hulls by hand; missingGroups() reports any
     * class still left unresolvable rather than letting its hulls
     * quietly vanish.
     */
    private const CAPITAL_GROUPS = [
        30 => 'Titan',
        659 => 'Supercarrier',
        485 => 'Dreadnought',
        4594 => 'Lancer',
        547 => 'Carrier',
        5120 => 'Command Carrier',
        1538 => 'Force Auxiliary',
        883 => 'Capital Industrial Ship',
        902 => 'Jump Freighter',
        513 => 'Freighter',
    ];

    /**
     * typeID => [groupID, hull name] for hulls whose class the SDE may
     * not know yet.
     *
     * SeAT pins the SDE import to a single build in its own manifest
     * (eveseat/resources sde.json), so `eve:update:sde` does not track
     * CCP releases - at the time of writing every SeAT install is on the
     * July 2025 build, which predates Command Carriers entirely. Rather
     * than show an empty column until upstream bumps that manifest, the
     * few affected hulls are named here.
     *
     * The SDE always wins where it has an answer, so these entries stop
     * being consulted the moment an install imports a build that knows
     * them - nothing to clean up later.
     */
    private const FALLBACK_HULLS = [
        92822 => [5120, 'Salvation'],
        92823 => [5120, 'Simurgh'],
        92824 => [5120, 'Gaia'],
        92825 => [5120, 'Ymir'],
    ];

    /** Guards against a cycle in the containment tree. */
    private const MAX_NESTING_DEPTH = 20;

    private const CACHE_KEY = 'member-engagement:capitals';

    /**
     * Held a little longer than the three-hour refresh, so a refresh that
     * runs late doesn't leave the page with nothing to show.
     */
    private const CACHE_TTL_MINUTES = 240;

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
     * A hull id for a class, from the built-in table - the only way to
     * name or picture a class whose hulls are not in this install's SDE.
     */
    public function fallbackHullFor(int $groupId): ?int
    {
        foreach (self::FALLBACK_HULLS as $typeId => $hull) {
            if ($hull[0] === $groupId) {
                return (int) $typeId;
            }
        }

        return null;
    }

    /**
     * The cached dataset. Walking every linked character's asset tree is
     * too slow to do on a page load once an install has a few hundred
     * characters, so the page reads a snapshot refreshed on a schedule.
     *
     * Computes on demand if the cache is empty - a fresh install or a
     * flushed cache shows real figures rather than an empty page, it just
     * pays for them once.
     *
     * @return array{types: array, accounts: array, missing_groups: array, generated_at: ?Carbon}
     */
    public function cached(): array
    {
        $payload = Cache::get(self::CACHE_KEY);

        if (!is_array($payload)) {
            $payload = $this->refresh();
        }

        return [
            'types' => $payload['types'] ?? [],
            'accounts' => $payload['accounts'] ?? [],
            'missing_groups' => $payload['missing_groups'] ?? [],
            'generated_at' => isset($payload['generated_at'])
                ? Carbon::parse($payload['generated_at'])
                : null,
        ];
    }

    /** Recompute and store. Returns what was stored. */
    public function refresh(): array
    {
        $payload = $this->build() + ['generated_at' => Carbon::now()->toIso8601String()];

        Cache::put(self::CACHE_KEY, $payload, Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $payload;
    }

    /**
     * @return array{types: array, accounts: array, missing_groups: array}
     */
    public function build(): array
    {
        $missingGroups = $this->missingGroups();
        $groups = $this->consolidation->allAccountGroups();

        if (empty($groups)) {
            return ['types' => [], 'accounts' => [], 'missing_groups' => $missingGroups];
        }

        $characterToMain = [];
        foreach ($groups as $mainId => $characterIds) {
            foreach ($characterIds as $characterId) {
                $characterToMain[$characterId] = $mainId;
            }
        }

        $hulls = $this->hulls(array_keys($characterToMain));

        if ($hulls->isEmpty()) {
            return ['types' => [], 'accounts' => [], 'missing_groups' => $missingGroups];
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
            'in_space' => (int) $hull->in_space === 1,
            'region' => $hull->region_name ?? 'Unknown',
            'quantity' => (int) $hull->quantity,
        ]);

        return [
            'types' => $this->byType($hulls, $accountNames),
            'accounts' => $this->byAccount($hulls, $groups, $accountNames),
            'missing_groups' => $missingGroups,
        ];
    }

    /**
     * Tracked hull classes this install's SDE has never heard of, as
     * groupID => name.
     *
     * CCP adds capital classes (Lancers, Command Carriers)
     * faster than most installs re-import the SDE, and because the hull
     * query joins invTypes the hulls simply aren't returned - no error,
     * just a missing column. Reporting it makes the cause obvious.
     *
     * @return array<int, string>
     */
    public function missingGroups(): array
    {
        $known = DB::table('invGroups')
            ->whereIn('groupID', array_keys(self::CAPITAL_GROUPS))
            ->pluck('groupID')
            ->map(fn($id) => (int) $id)
            ->all();

        $covered = array_merge(
            $known,
            array_map(fn(array $hull) => $hull[0], self::FALLBACK_HULLS)
        );

        return array_diff_key(self::CAPITAL_GROUPS, array_flip($covered));
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
                            'group_id' => $hull->group_id,
                            'group_name' => $hull->group_name,
                            'character_name' => $hull->character_name,
                            'system' => $hull->system,
                            'in_space' => $hull->in_space,
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
     * A CASE expression mapping type_id to the built-in group id (field 1)
     * or hull name (field 2), for use where the SDE has nothing to say.
     *
     * The type ids and names are constants in this class, never user
     * input, so inlining them is safe - and it has to be inlined, since
     * the query is raw SQL driving a recursive CTE.
     */
    private function fallbackCase(int $field): string
    {
        if (empty(self::FALLBACK_HULLS)) {
            return 'NULL';
        }

        $cases = '';

        foreach (self::FALLBACK_HULLS as $typeId => $hull) {
            $value = $field === 1
                ? (int) $hull[0]
                : "'" . str_replace("'", "''", $hull[1]) . "'";

            $cases .= sprintf(' WHEN %d THEN %s', (int) $typeId, $value);
        }

        return "CASE r.type_id{$cases} END";
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
        $fallbackGroups = $this->fallbackCase(1);
        $fallbackNames = $this->fallbackCase(2);

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
            SELECT * FROM (
                SELECT r.character_id, r.type_id, r.quantity,
                       COALESCE(it.typeName, {$fallbackNames}) AS type_name,
                       COALESCE(ig.groupID, {$fallbackGroups}) AS group_id,
                       ig.groupName AS group_name,
                       ss.name AS system_name,
                       reg.name AS region_name,
                       CASE WHEN ss.system_id = r.root_location THEN 1 ELSE 0 END AS in_space
                FROM roots r
                LEFT JOIN invTypes it ON it.typeID = r.type_id
                LEFT JOIN invGroups ig ON ig.groupID = it.groupID
                LEFT JOIN universe_structures us ON us.structure_id = r.root_location
                LEFT JOIN mapDenormalize md ON md.itemID = r.root_location
                LEFT JOIN staStations sta ON sta.stationID = r.root_location
                -- root_location last: a ship left in space reports the
                -- solar system as its location, and the SDE row for a
                -- system carries a region but leaves solarSystemID null,
                -- so the three joins above all come back empty for it.
                LEFT JOIN solar_systems ss
                       ON ss.system_id = COALESCE(us.solar_system_id, md.solarSystemID,
                                                  sta.solarSystemID, r.root_location)
                LEFT JOIN regions reg ON reg.region_id = ss.region_id
            ) resolved
            WHERE resolved.group_id IN ({$groupIds})
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

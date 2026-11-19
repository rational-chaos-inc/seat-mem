<?php

namespace RCI\MemberEngagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns an asset's location id into a system and a region.
 *
 * Every asset query in this plugin needs the same answer, and the sources
 * it has to consult are a moving target, so the join chain lives here
 * rather than being copied into each query.
 *
 * Why it is a chain rather than one lookup: a location id can be a player
 * structure SeAT has resolved, an NPC station, a celestial, or - for a
 * ship left in space - a solar system in its own right. No single table
 * covers all four, and which tables exist at all depends on how the
 * install imported its SDE.
 *
 * CCP is replacing the SDE zip with an HTTP service that drops
 * mapDenormalize and staStations in favour of per-kind tables
 * (mapSolarSystems, npcStations, mapPlanets and so on). SeAT derives
 * regions, solar_systems, constellations, planets and moons FROM
 * mapDenormalize, so that import has to change before this plugin can do
 * anything about it. What this class can do is make each source optional:
 * every table is probed before it is joined, so an install whose schema
 * has moved on resolves what it still can and reports the rest as
 * unknown, rather than every asset query throwing.
 *
 * @see https://developers.eveonline.com/blog/the-sde-over-http-static-data-dynamically
 */
class LocationResolutionService
{
    /**
     * Where a system id can come from, in preference order:
     * alias => [table, join column, the column holding the system id].
     *
     * npcStations is the new SDE's replacement for staStations. It is
     * listed because SeAT names its SDE tables after the SDE's own, so
     * that is the likely name if and when the import moves - and probing
     * for it costs nothing on an install that has never heard of it.
     */
    private const SYSTEM_SOURCES = [
        'us' => ['universe_structures', 'structure_id', 'solar_system_id'],
        'md' => ['mapDenormalize', 'itemID', 'solarSystemID'],
        'sta' => ['staStations', 'stationID', 'solarSystemID'],
        'npc' => ['npcStations', 'stationID', 'solarSystemID'],
    ];

    /**
     * Tables that carry a region id outright, used only to cover a system
     * that solar_systems cannot place.
     */
    private const REGION_SOURCES = [
        'md' => 'regionID',
        'sta' => 'regionID',
    ];

    /** @var array<string, bool>|null */
    private ?array $present = null;

    /** Does this install have that table? Probed once per request. */
    public function has(string $table): bool
    {
        if ($this->present === null) {
            $this->present = [];
        }

        return $this->present[$table] ??= Schema::hasTable($table);
    }

    /**
     * The JOIN clauses, resolving `{$alias}.{$column}` to a location.
     *
     * Defines whichever of these the install can support: the sources
     * above, `loc_system` (solar_systems) and `loc_region` (regions).
     * Aliases for absent tables are simply not emitted, so every
     * expression below has to tolerate their absence - which is what
     * sqlAlias() is for.
     */
    public function joins(string $alias = 'r', string $column = 'root_location'): string
    {
        $target = "{$alias}.{$column}";
        $lines = [];

        foreach (self::SYSTEM_SOURCES as $as => [$table, $key, $_]) {
            if ($this->has($table)) {
                $lines[] = "LEFT JOIN {$table} {$as} ON {$as}.{$key} = {$target}";
            }
        }

        // A celestial resolved through the structure's own system, which
        // is how an Upwell structure in a system with no celestial row of
        // its own still lands in a region.
        if ($this->has('mapDenormalize') && $this->has('universe_structures')) {
            $lines[] = 'LEFT JOIN mapDenormalize md_struct ON md_struct.itemID = us.solar_system_id';
        }

        if ($this->has('solar_systems')) {
            $lines[] = 'LEFT JOIN solar_systems loc_system ON loc_system.system_id = '
                . $this->systemIdExpression($target);
        }

        if ($this->has('regions') && $this->has('solar_systems')) {
            $lines[] = 'LEFT JOIN regions loc_region ON loc_region.region_id = loc_system.region_id';
        }

        return implode("\n            ", $lines);
    }

    /**
     * The system id for the location, as SQL.
     *
     * The target column comes last on purpose: when nothing above has
     * placed it, the id may be a solar system itself, which is exactly
     * the case of a ship logged off in space.
     */
    public function systemIdExpression(string $target = 'r.root_location'): string
    {
        $terms = [];

        foreach (self::SYSTEM_SOURCES as $as => [$table, $_, $systemColumn]) {
            if ($this->has($table)) {
                $terms[] = "{$as}.{$systemColumn}";
            }
        }

        $terms[] = $target;

        return 'COALESCE(' . implode(', ', $terms) . ')';
    }

    /** The region id for the location, as SQL, or NULL where nothing can say. */
    public function regionIdExpression(): string
    {
        $terms = [];

        if ($this->has('solar_systems')) {
            $terms[] = 'loc_system.region_id';
        }

        foreach (self::REGION_SOURCES as $as => $regionColumn) {
            $table = self::SYSTEM_SOURCES[$as][0];

            if ($this->has($table)) {
                $terms[] = "{$as}.{$regionColumn}";
            }
        }

        if ($this->has('mapDenormalize') && $this->has('universe_structures')) {
            $terms[] = 'md_struct.regionID';
        }

        return $this->coalesceOrNull($terms);
    }

    /** The system name, as SQL. */
    public function systemNameExpression(): string
    {
        return $this->coalesceOrNull($this->has('solar_systems') ? ['loc_system.name'] : []);
    }

    /** The region name, as SQL. */
    public function regionNameExpression(): string
    {
        return $this->coalesceOrNull(
            $this->has('regions') && $this->has('solar_systems') ? ['loc_region.name'] : []
        );
    }

    /**
     * Whether the location id is itself a solar system - a hull left in
     * space rather than docked anywhere.
     */
    public function inSpaceExpression(string $target = 'r.root_location'): string
    {
        if (!$this->has('solar_systems')) {
            return '0';
        }

        return "CASE WHEN loc_system.system_id = {$target} THEN 1 ELSE 0 END";
    }

    /**
     * Names for a set of location ids, for the drill-down to group by.
     *
     * Player structures first, because a resolved name beats a composed
     * one; then a solar system, which is a place rather than a container;
     * then the SDE's own names. The new SDE has no station names at all -
     * npcStations carries operationID and orbitID instead - so a future
     * install may only be able to name a station through ESI, which is
     * what the labelled fallback leaves room for.
     *
     * @param  array  $locationIds
     * @return array<string, array{name: string, type_id: int|null}>
     */
    public function names(array $locationIds, string $fallbackLabel): array
    {
        $ids = collect($locationIds)->map(fn($id) => (string) $id)->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        $structures = $this->lookup('universe_structures', 'structure_id', $ids, ['name', 'type_id']);
        $systems = $this->lookup('solar_systems', 'system_id', $ids, ['name']);
        $celestials = $this->lookup('mapDenormalize', 'itemID', $ids, ['itemName', 'typeID']);
        $stations = $this->lookup('staStations', 'stationID', $ids, ['stationName', 'stationTypeID']);
        $npcStations = $this->lookup('npcStations', 'stationID', $ids, ['typeID']);

        return $ids->mapWithKeys(function ($id) use (
            $structures, $systems, $celestials, $stations, $npcStations, $fallbackLabel
        ) {
            if ($row = $structures->get($id)) {
                return [$id => ['name' => $row->name, 'type_id' => (int) $row->type_id]];
            }

            if ($row = $systems->get($id)) {
                return [$id => ['name' => 'In space - ' . $row->name, 'type_id' => null]];
            }

            if (($row = $celestials->get($id)) && $row->itemName) {
                return [$id => ['name' => $row->itemName, 'type_id' => (int) $row->typeID]];
            }

            if ($row = $stations->get($id)) {
                return [$id => ['name' => $row->stationName, 'type_id' => (int) $row->stationTypeID]];
            }

            // Known to be a station, but this schema cannot name it.
            if ($row = $npcStations->get($id)) {
                return [$id => ['name' => 'Station ' . $id, 'type_id' => (int) $row->typeID]];
            }

            return [$id => ['name' => $fallbackLabel . ' ' . $id, 'type_id' => null]];
        })->all();
    }

    /**
     * Rows from a table that may not exist, keyed by the given column as
     * a string.
     */
    private function lookup(string $table, string $key, $ids, array $columns)
    {
        if (!$this->has($table)) {
            return collect();
        }

        return DB::table($table)
            ->whereIn($key, $ids)
            ->get(array_merge([$key], $columns))
            ->keyBy(fn($row) => (string) $row->{$key});
    }

    /** @param  string[]  $terms */
    private function coalesceOrNull(array $terms): string
    {
        if (empty($terms)) {
            return 'NULL';
        }

        return count($terms) === 1 ? $terms[0] : 'COALESCE(' . implode(', ', $terms) . ')';
    }
}

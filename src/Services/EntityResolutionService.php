<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\CharacterAffiliation;
use RCI\MemberEngagement\Models\EntityName;

/**
 * Resolves character names, corporations and alliances via ESI's public,
 * unauthenticated endpoints. SeAT's own character_infos/corporation_infos
 * tables only cover entities it has actively synced (characters with a
 * token, corporations with director access); the vast majority of
 * characters referenced in wallet journals and killmails never appear
 * there, so most lookups have to go to ESI directly. Results are cached
 * locally (member_engagement_entity_names / _character_affiliations) and
 * re-resolved periodically rather than on every page load.
 */
class EntityResolutionService
{
    private const ESI_BASE = 'https://esi.evetech.net/latest';
    private const BATCH_SIZE = 1000;
    private const CACHE_TTL_DAYS = 7;

    /**
     * Resolve names and affiliations for the given character IDs, skipping
     * any already cached within the TTL. Safe to call with IDs that are
     * already resolved - they're filtered out before hitting ESI.
     */
    public function resolveCharacters(array $characterIds): int
    {
        $characterIds = array_values(array_unique(array_filter($characterIds)));
        if (empty($characterIds)) {
            return 0;
        }

        $staleThreshold = Carbon::now()->subDays(self::CACHE_TTL_DAYS);
        $alreadyResolved = CharacterAffiliation::whereIn('character_id', $characterIds)
            ->where('resolved_at', '>=', $staleThreshold)
            ->pluck('character_id')
            ->toArray();

        $toResolve = array_values(array_diff($characterIds, $alreadyResolved));
        if (empty($toResolve)) {
            return 0;
        }

        $resolvedCount = 0;
        $discoveredCorpIds = [];
        $discoveredAllianceIds = [];

        foreach (array_chunk($toResolve, self::BATCH_SIZE) as $batch) {
            $affiliations = $this->fetchAffiliations($batch);

            $now = Carbon::now();
            foreach ($affiliations as $row) {
                CharacterAffiliation::updateOrCreate(
                    ['character_id' => $row['character_id']],
                    [
                        'corporation_id' => $row['corporation_id'] ?? null,
                        'alliance_id' => $row['alliance_id'] ?? null,
                        'faction_id' => $row['faction_id'] ?? null,
                        'resolved_at' => $now,
                    ]
                );

                if (!empty($row['corporation_id'])) {
                    $discoveredCorpIds[] = $row['corporation_id'];
                }
                if (!empty($row['alliance_id'])) {
                    $discoveredAllianceIds[] = $row['alliance_id'];
                }

                $resolvedCount++;
            }
        }

        // Resolve names for the characters themselves, plus any newly
        // discovered corporations/alliances not already cached.
        $this->resolveNames($toResolve, $discoveredCorpIds, $discoveredAllianceIds);

        return $resolvedCount;
    }

    private function resolveNames(array $characterIds, array $corporationIds, array $allianceIds): void
    {
        $allIds = array_values(array_unique(array_merge($characterIds, $corporationIds, $allianceIds)));
        if (empty($allIds)) {
            return;
        }

        $staleThreshold = Carbon::now()->subDays(self::CACHE_TTL_DAYS);
        $alreadyResolved = EntityName::whereIn('entity_id', $allIds)
            ->where('resolved_at', '>=', $staleThreshold)
            ->pluck('entity_id')
            ->toArray();

        $toResolve = array_values(array_diff($allIds, $alreadyResolved));
        if (empty($toResolve)) {
            return;
        }

        foreach (array_chunk($toResolve, self::BATCH_SIZE) as $batch) {
            $names = $this->fetchNames($batch);

            $now = Carbon::now();
            foreach ($names as $row) {
                EntityName::updateOrCreate(
                    ['entity_id' => $row['id']],
                    [
                        'name' => $row['name'],
                        'category' => $row['category'],
                        'resolved_at' => $now,
                    ]
                );
            }
        }
    }

    private function fetchAffiliations(array $characterIds): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout(15)
                ->post(self::ESI_BASE . '/characters/affiliation/?datasource=tranquility', $characterIds);

            if (!$response->successful()) {
                Log::warning('ESI affiliation lookup failed', [
                    'status' => $response->status(),
                    'count' => count($characterIds),
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('ESI affiliation lookup error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function fetchNames(array $ids): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                ->timeout(15)
                ->post(self::ESI_BASE . '/universe/names/?datasource=tranquility', $ids);

            if (!$response->successful()) {
                Log::warning('ESI name lookup failed', [
                    'status' => $response->status(),
                    'count' => count($ids),
                ]);
                return [];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('ESI name lookup error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function userAgent(): string
    {
        return 'SeAT-MemberEngagementModule (https://github.com/rational-chaos-inc/seat-mep; mattallum1974@gmail.com)';
    }

    /**
     * Find character IDs referenced by activities that have never been
     * resolved (or are past the cache TTL), for the periodic resolve command.
     */
    public function findUnresolvedCharacterIds(int $limit = 5000): array
    {
        $staleThreshold = Carbon::now()->subDays(self::CACHE_TTL_DAYS);

        return DB::table('member_engagement_activities')
            ->whereNotIn('character_id', function ($query) use ($staleThreshold) {
                $query->select('character_id')
                    ->from('member_engagement_character_affiliations')
                    ->where('resolved_at', '>=', $staleThreshold);
            })
            ->where('character_id', '>', 0)
            ->distinct()
            ->limit($limit)
            ->pluck('character_id')
            ->toArray();
    }

    /**
     * Attach character_name, corporation_name, alliance_name and a combined
     * display_name ("Name (Corp) [Alliance]") to each item in a collection
     * that has a character_id property/attribute (e.g. Activity models).
     * Batches all lookups - safe to call with hundreds of rows. Reads only
     * from already-resolved local data (SeAT's own tables plus our ESI
     * cache); does not make live ESI calls itself, so this stays fast
     * enough for a page load.
     */
    public function attachDisplayNames($items): void
    {
        $charIds = collect($items)->pluck('character_id')->filter()->unique()->values();
        if ($charIds->isEmpty()) {
            return;
        }

        $charNamesFromInfos = DB::table('character_infos')
            ->whereIn('character_id', $charIds)
            ->pluck('name', 'character_id');
        $charNamesFromCache = EntityName::whereIn('entity_id', $charIds)->pluck('name', 'entity_id');

        $affiliations = CharacterAffiliation::whereIn('character_id', $charIds)->get()->keyBy('character_id');

        $corpIds = $affiliations->pluck('corporation_id')
            ->merge(collect($items)->pluck('corporation_id'))
            ->filter()->unique()->values();
        $allianceIds = $affiliations->pluck('alliance_id')->filter()->unique()->values();

        $corpNamesFromInfos = DB::table('corporation_infos')->whereIn('corporation_id', $corpIds)->pluck('name', 'corporation_id');
        $corpNamesFromCache = EntityName::whereIn('entity_id', $corpIds)->pluck('name', 'entity_id');

        $allianceNamesFromInfos = DB::table('alliances')->whereIn('alliance_id', $allianceIds)->pluck('name', 'alliance_id');
        $allianceNamesFromCache = EntityName::whereIn('entity_id', $allianceIds)->pluck('name', 'entity_id');

        foreach ($items as $item) {
            $charId = $item->character_id;

            $name = $charNamesFromInfos[$charId]
                ?? $charNamesFromCache[$charId]
                ?? $item->metadata['character_name']
                ?? null;

            $affiliation = $affiliations[$charId] ?? null;
            $corpId = $affiliation->corporation_id ?? $item->corporation_id ?? null;
            $allianceId = $affiliation->alliance_id ?? null;

            $corpName = $corpId ? ($corpNamesFromInfos[$corpId] ?? $corpNamesFromCache[$corpId] ?? null) : null;
            $allianceName = $allianceId ? ($allianceNamesFromInfos[$allianceId] ?? $allianceNamesFromCache[$allianceId] ?? null) : null;

            $item->character_name = $name;
            $item->corporation_name = $corpName;
            $item->alliance_name = $allianceName;
            $item->display_name = $this->formatDisplayName($name, $charId, $corpName, $allianceName);
        }
    }

    private function formatDisplayName(?string $name, int $charId, ?string $corpName, ?string $allianceName): string
    {
        $label = $name ?? "Unknown ({$charId})";

        if ($corpName) {
            $label .= " ({$corpName})";
        }
        if ($allianceName) {
            $label .= " [{$allianceName}]";
        }

        return $label;
    }
}

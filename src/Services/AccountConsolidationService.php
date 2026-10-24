<?php

namespace RCI\MemberEngagement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves each character to the "main character" of the EVE/SeAT account
 * it belongs to, for consolidating alts under one entry on rankings.
 *
 * EVE's ESI has no endpoint that reveals which characters share an account
 * - that's deliberately not exposed. The only linkage SeAT (or anyone) can
 * know is for characters whose owner has personally authenticated them
 * into SeAT (refresh_tokens.character_id -> user_id -> users.main_character_id).
 * Most characters referenced in corp-wide activity data (killmails, wallet
 * journal, industry jobs) were never individually authenticated, so they
 * have no linkage at all - resolveGroups() deliberately omits them rather
 * than treating them as a group of one, since the Corporation View
 * leaderboards are scoped to registered SeAT members and their known
 * alts only.
 */
class AccountConsolidationService
{
    /**
     * @param int[] $characterIds
     * @return array<int, int> character_id => main character_id, for
     *         characters linked to a registered SeAT account only.
     *         Characters with no such link are simply absent from the
     *         returned map - callers should treat that as "exclude".
     */
    public function resolveGroups(array $characterIds): array
    {
        $characterIds = array_values(array_unique(array_filter($characterIds)));
        if (empty($characterIds)) {
            return [];
        }

        $userIdByCharacter = DB::table('refresh_tokens')
            ->whereIn('character_id', $characterIds)
            ->whereNull('deleted_at')
            ->pluck('user_id', 'character_id');

        if ($userIdByCharacter->isEmpty()) {
            return [];
        }

        $mainCharacterByUser = DB::table('users')
            ->whereIn('id', $userIdByCharacter->unique()->values())
            ->whereNotNull('main_character_id')
            ->pluck('main_character_id', 'id');

        $map = [];
        foreach ($userIdByCharacter as $characterId => $userId) {
            if (isset($mainCharacterByUser[$userId])) {
                $map[$characterId] = (int) $mainCharacterByUser[$userId];
            }
        }

        // Same primary-corporation scope the account listings use, so a
        // leaderboard can't show someone the Heartbeat list has dropped.
        if ($this->primaryCorporationId() !== null) {
            $eligible = $this->allAccountGroups();
            $map = array_filter($map, fn($mainId) => isset($eligible[$mainId]));
        }

        return $map;
    }

    /**
     * All character IDs belonging to a SeAT user's account: their linked
     * characters (refresh_tokens.user_id) plus their designated main
     * character, included even if its own token has since expired/been
     * revoked - the character ID itself stays valid for activity lookups
     * regardless of whether SeAT can currently still pull ESI data for it.
     *
     * The one exception: if that main character is *currently* linked to a
     * *different* user's account (a stale main_character_id pointer left
     * over from before the character moved accounts - confirmed to happen
     * on live data), it's excluded here rather than counted for both users.
     * Whoever actively holds the token wins; resolveGroups() makes the same
     * choice for consistency between Corporation View and My Activities.
     *
     * @return int[]
     */
    public function charactersForUser(int $userId, ?int $mainCharacterId = null): array
    {
        $linked = DB::table('refresh_tokens')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn($id) => (int) $id)
            ->all();

        if ($mainCharacterId && !in_array((int) $mainCharacterId, $linked, true)) {
            $ownedByAnotherAccount = DB::table('refresh_tokens')
                ->where('character_id', $mainCharacterId)
                ->where('user_id', '!=', $userId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$ownedByAnotherAccount) {
                $linked[] = (int) $mainCharacterId;
            }
        }

        return array_values(array_unique($linked));
    }

    /**
     * Every registered SeAT account's full character group (main + linked
     * alts), for listings that need to show ALL registered members -
     * including ones with zero activity - rather than only those who show
     * up in activity data (e.g. the Heartbeat list).
     *
     * @return array<int, int[]> main character_id => character_id[]
     */
    public function allAccountGroups(): array
    {
        // main_character_id = 0 is SeAT's own sentinel for accounts with no
        // real EVE character attached (e.g. the built-in "admin" superuser)
        // - treat that the same as null, not as a character to track.
        $users = DB::table('users')
            ->whereNotNull('main_character_id')
            ->where('main_character_id', '>', 0)
            ->get(['id', 'main_character_id']);
        if ($users->isEmpty()) {
            return [];
        }

        $tokens = DB::table('refresh_tokens')->whereNull('deleted_at')->get(['user_id', 'character_id']);
        $linkedByUser = $tokens->groupBy('user_id');
        $ownerByCharacter = $tokens->pluck('user_id', 'character_id');

        $groups = [];
        foreach ($users as $user) {
            $linked = ($linkedByUser->get($user->id) ?? collect())
                ->pluck('character_id')
                ->map(fn($id) => (int) $id)
                ->all();

            $mainId = (int) $user->main_character_id;

            if (!in_array($mainId, $linked, true)) {
                $currentOwner = $ownerByCharacter[$mainId] ?? null;

                if ($currentOwner === null || (int) $currentOwner === (int) $user->id) {
                    $linked[] = $mainId;
                }
            }

            if (!empty($linked)) {
                $groups[$mainId] = array_values(array_unique($linked));
            }
        }

        return $this->inPrimaryCorporation($groups);
    }

    /**
     * The corporation this install is actually about, as picked in Corp
     * Wallet Manager. Null when that module isn't installed or hasn't been
     * pointed at a corporation yet.
     */
    public function primaryCorporationId(): ?int
    {
        if (!Schema::hasTable('corpwalletmanager_settings')) {
            return null;
        }

        $value = DB::table('corpwalletmanager_settings')
            ->where('key', 'selected_corporation_id')
            ->value('value');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Drops accounts with no character currently in the primary
     * corporation - an alliance-wide SeAT otherwise fills the dashboards
     * with people from corporations this install doesn't speak for.
     *
     * One character is enough: alts parked in a holding corp shouldn't
     * disqualify a main who is right where they should be. With no primary
     * corporation configured, nothing is filtered.
     *
     * @param  array<int, int[]>  $groups
     * @return array<int, int[]>
     */
    private function inPrimaryCorporation(array $groups): array
    {
        $primary = $this->primaryCorporationId();

        if ($primary === null || empty($groups)) {
            return $groups;
        }

        $corpByCharacter = DB::table('character_affiliations')
            ->whereIn('character_id', array_merge(...array_values($groups)))
            ->pluck('corporation_id', 'character_id');

        return array_filter($groups, function (array $characterIds) use ($corpByCharacter, $primary) {
            foreach ($characterIds as $characterId) {
                if ((int) ($corpByCharacter[$characterId] ?? 0) === $primary) {
                    return true;
                }
            }

            return false;
        });
    }
}

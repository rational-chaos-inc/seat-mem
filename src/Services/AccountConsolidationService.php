<?php

namespace RCI\MemberEngagement\Services;

use Illuminate\Support\Facades\DB;

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
}

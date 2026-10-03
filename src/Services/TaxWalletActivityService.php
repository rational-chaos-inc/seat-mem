<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;
use Seat\Eveapi\Models\Wallet\CorporationWalletJournal;

class TaxWalletActivityService
{
    public function collectTaxWallet(int $corporationId, ?Carbon $since = null): Collection
    {
        $activities = collect();

        try {
            // If no since date provided, collect from last 30 days
            if (!$since) {
                $since = Carbon::now()->subDays(30);
            }

            $walletEntries = $this->getWalletJournalBounties($corporationId, $since);

            foreach ($walletEntries as $entry) {
                $activity = $this->parseWalletEntry($entry, $corporationId);
                if ($activity) {
                    $activities->push($activity);
                }
            }

            Log::info("Collected {$activities->count()} tax wallet activities for corporation {$corporationId}");
        } catch (\Exception $e) {
            Log::error("Failed to collect tax wallet activities for corporation {$corporationId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $activities;
    }

    private function getWalletJournalBounties(int $corporationId, Carbon $since): Collection
    {
        try {
            return CorporationWalletJournal::where('corporation_id', $corporationId)
                ->where('ref_type', 'bounty')
                ->where('date', '>=', $since)
                ->orderBy('date', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to query corporation wallet journal", [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    private function parseWalletEntry($entry, int $corporationId): ?Activity
    {
        try {
            // owner_id1 is the character who earned the bounty
            $characterId = $entry->owner_id1 ?? null;

            if (!$characterId) {
                Log::warning("Wallet entry missing owner_id1", [
                    'ref_id' => $entry->ref_id ?? null,
                ]);
                return null;
            }

            $amount = abs($entry->amount ?? 0);

            if ($amount <= 0) {
                return null;
            }

            return Activity::make([
                'activity_timestamp' => $entry->date,
                'character_id' => $characterId,
                'corporation_id' => $corporationId,
                'alliance_id' => null, // Could be fetched from character if needed
                'activity_type' => 'tax_wallet',
                'source_id' => "wallet_{$entry->ref_id}_{$characterId}",
                'metadata' => [
                    'amount' => $amount,
                    'tax_amount' => $entry->tax_amount ?? 0,
                    'reason' => $entry->reason ?? 'NPC Bounty',
                    'owner_name' => $entry->owner_name1 ?? 'Unknown',
                    'ref_id' => $entry->ref_id ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to parse wallet entry", [
                'error' => $e->getMessage(),
                'entry_ref_id' => $entry->ref_id ?? null,
            ]);
            return null;
        }
    }

    public function getTaxWalletForCorporation(int $corporationId, ?Carbon $since = null): Collection
    {
        return $this->collectTaxWallet($corporationId, $since);
    }

    public function getTaxWalletForCharacter(int $characterId, ?Carbon $since = null): Collection
    {
        if (!$since) {
            $since = Carbon::now()->subDays(30);
        }

        try {
            // Get activities created from wallet entries where this character was the earner
            return Activity::where('activity_type', 'tax_wallet')
                ->where('character_id', $characterId)
                ->where('activity_timestamp', '>=', $since)
                ->orderBy('activity_timestamp', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to retrieve tax wallet for character", [
                'character_id' => $characterId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}

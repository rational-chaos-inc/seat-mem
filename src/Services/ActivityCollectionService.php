<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;
use RCI\MemberEngagement\Events\ActivitiesCollected;
use Seat\Eveapi\Models\Character\CharacterInfo;

class ActivityCollectionService
{
    public function __construct(
        private ESIActivityService $esiService,
        private TaxWalletActivityService $taxWalletService,
        private MiningActivityService $miningService,
    ) {
    }

    public function collectForCorporation(int $corporationId, ?Carbon $since = null): void
    {
        Log::info("Starting activity collection for corporation {$corporationId}");

        try {
            $startTime = microtime(true);

            // Get all active characters for the corporation
            $characters = $this->getCharactersForCorporation($corporationId);

            if ($characters->isEmpty()) {
                Log::warning("No characters found for corporation {$corporationId}");
                return;
            }

            Log::debug("Found {$characters->count()} characters with valid tokens", [
                'corporation_id' => $corporationId,
            ]);

            $activities = collect();

            // Collect from each data source
            try {
                $killLossCount = $this->esiService->collectKillsAndLosses($characters)->count();
                $activities = $activities->merge($this->esiService->collectKillsAndLosses($characters));
                Log::debug("Collected {$killLossCount} kills/losses");
            } catch (\Exception $e) {
                Log::error("ESI collection failed", ['error' => $e->getMessage()]);
            }

            try {
                $miningCount = $this->miningService->collectMiningData($characters, $since)->count();
                $activities = $activities->merge($this->miningService->collectMiningData($characters, $since));
                Log::debug("Collected {$miningCount} mining activities");
            } catch (\Exception $e) {
                Log::error("Mining collection failed", ['error' => $e->getMessage()]);
            }

            try {
                $taxCount = $this->taxWalletService->collectTaxWallet($corporationId, $since)->count();
                $activities = $activities->merge($this->taxWalletService->collectTaxWallet($corporationId, $since));
                Log::debug("Collected {$taxCount} tax wallet activities");
            } catch (\Exception $e) {
                Log::error("Tax wallet collection failed", ['error' => $e->getMessage()]);
            }

            // Save activities and link to users
            $saved = $this->saveActivities($activities);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("Collected {$saved} activities for corporation {$corporationId}", [
                'duration_seconds' => $duration,
                'characters' => $characters->count(),
            ]);

            // Publish local event
            event(new ActivitiesCollected($saved, $corporationId));
        } catch (\Exception $e) {
            Log::error("Error collecting activities for corporation {$corporationId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function getCharactersForCorporation(int $corporationId): Collection
    {
        // Query SeAT for all characters in the corporation with valid tokens
        return CharacterInfo::where('corporation_id', $corporationId)
            ->whereNotNull('refresh_token')
            ->get();
    }

    private function saveActivities(Collection $activities): int
    {
        $saved = 0;
        $skipped = 0;

        foreach ($activities as $activity) {
            try {
                // Use updateOrCreate to handle duplicates gracefully
                // The source_id unique constraint will prevent duplicates
                $saved += Activity::updateOrCreate(
                    [
                        'activity_type' => $activity->activity_type,
                        'source_id' => $activity->source_id,
                    ],
                    $activity->getAttributes()
                ) ? 1 : 0;
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation gracefully
                if (str_contains($e->getMessage(), 'unique')) {
                    $skipped++;
                    continue;
                }

                Log::error("Failed to save activity", [
                    'error' => $e->getMessage(),
                    'activity' => $activity->toArray(),
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to save activity", [
                    'error' => $e->getMessage(),
                    'activity' => $activity->toArray(),
                ]);
            }
        }

        if ($skipped > 0) {
            Log::debug("Skipped {$skipped} duplicate activities");
        }

        return $saved;
    }

    public function collectForUser(int $userId, ?Carbon $since = null): Collection
    {
        Log::info("Collecting activities for user {$userId}");

        try {
            $userModel = config('auth.providers.users.model');
            $user = $userModel::find($userId);

            if (!$user) {
                Log::warning("User not found", ['user_id' => $userId]);
                return collect();
            }

            // Get all characters linked to this user
            if (!method_exists($user, 'characters')) {
                Log::warning("User model does not have characters relationship", ['user_id' => $userId]);
                return collect();
            }

            $characters = $user->characters()->get();

            if ($characters->isEmpty()) {
                Log::debug("No characters linked to user", ['user_id' => $userId]);
                return collect();
            }

            $activities = collect();

            // Collect from each data source using character-specific queries
            foreach ($characters as $character) {
                $activities = $activities->merge($this->esiService->getKillmailsForCharacter($character->character_id));
                $activities = $activities->merge($this->miningService->getMiningForCharacter($character->character_id, $since));
                $activities = $activities->merge($this->taxWalletService->getTaxWalletForCharacter($character->character_id, $since));
            }

            Log::info("Collected {$activities->count()} activities for user {$userId}");

            return $activities;
        } catch (\Exception $e) {
            Log::error("Error collecting activities for user {$userId}", [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}

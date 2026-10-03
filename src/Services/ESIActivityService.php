<?php

namespace RCI\MemberEngagement\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;
use Seat\Eveapi\Models\Character\CharacterInfo;

class ESIActivityService
{
    private const ESI_BASE = 'https://esi.eveonline.com/latest';
    private const ENDPOINT = '/characters/{id}/killmails/recent';
    private const RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY = 2; // seconds

    public function collectKillsAndLosses(Collection $characters): Collection
    {
        $activities = collect();

        foreach ($characters as $character) {
            try {
                $characterId = $character instanceof CharacterInfo ? $character->character_id : $character;

                if (!$this->hasValidToken($character)) {
                    Log::warning("No ESI token for character {$characterId}");
                    continue;
                }

                $killmails = $this->fetchKillmails($character);

                foreach ($killmails as $killmail) {
                    $activity = $this->parseKillmail($killmail, $character);
                    if ($activity) {
                        $activities->push($activity);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to collect kills/losses for character", [
                    'character_id' => $character->character_id ?? $character,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $activities;
    }

    private function hasValidToken($character): bool
    {
        if ($character instanceof CharacterInfo) {
            return !empty($character->refresh_token);
        }
        return false;
    }

    private function fetchKillmails(CharacterInfo $character): array
    {
        $url = self::ESI_BASE . str_replace('{id}', $character->character_id, self::ENDPOINT);
        $token = $character->refresh_token;

        for ($attempt = 1; $attempt <= self::RETRY_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get($url);

                if ($response->status() === 420) {
                    // Rate limited - exponential backoff
                    if ($attempt < self::RETRY_ATTEMPTS) {
                        $delay = self::RETRY_DELAY ** $attempt;
                        Log::info("ESI rate limit, waiting {$delay}s before retry", ['character_id' => $character->character_id]);
                        sleep($delay);
                        continue;
                    }
                }

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                if ($response->status() === 401) {
                    Log::warning("Invalid ESI token for character {$character->character_id}");
                    return [];
                }

                if ($response->status() === 403) {
                    Log::warning("Insufficient ESI scopes for character {$character->character_id}");
                    return [];
                }

                // 5xx errors - retry
                if ($response->status() >= 500 && $attempt < self::RETRY_ATTEMPTS) {
                    sleep(self::RETRY_DELAY);
                    continue;
                }

                Log::error("ESI API error", [
                    'character_id' => $character->character_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            } catch (\Exception $e) {
                if ($attempt < self::RETRY_ATTEMPTS) {
                    Log::debug("ESI request failed, retrying", ['error' => $e->getMessage()]);
                    sleep(self::RETRY_DELAY);
                    continue;
                }
                throw $e;
            }
        }

        return [];
    }

    private function parseKillmail(array $killmailData, CharacterInfo $character): ?Activity
    {
        try {
            $killmailId = $killmailData['killmail_id'] ?? null;
            $killmailHash = $killmailData['killmail_hash'] ?? null;

            if (!$killmailId || !$killmailHash) {
                return null;
            }

            // Fetch full killmail details from ESI
            $killmailDetails = $this->fetchKillmailDetails($killmailId, $killmailHash);
            if (!$killmailDetails) {
                return null;
            }

            $isKill = $this->isCharacterKill($killmailDetails, $character->character_id);
            $iskValue = $killmailDetails['victim']['damage_taken'] ?? 0;

            // For losses, the victim's ship value is what was lost
            if (!$isKill) {
                $iskValue = $killmailDetails['victim']['ship_type_id'] ?? 0; // We'll need to calculate value from type_id
            }

            return Activity::make([
                'activity_timestamp' => $this->parseTimestamp($killmailDetails['killmail_time']),
                'character_id' => $character->character_id,
                'corporation_id' => $character->corporation_id,
                'alliance_id' => $character->alliance_id,
                'activity_type' => $isKill ? 'pvp_kill' : 'pvp_loss',
                'source_id' => "{$killmailId}_{$killmailHash}",
                'metadata' => [
                    'killmail_id' => $killmailId,
                    'value' => $iskValue,
                    'ship_type_id' => $killmailDetails['victim']['ship_type_id'] ?? null,
                    'victim_name' => $killmailDetails['victim']['character_name'] ?? null,
                    'attacker_count' => count($killmailDetails['attackers'] ?? []),
                    'system_id' => $killmailDetails['solar_system_id'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to parse killmail", [
                'error' => $e->getMessage(),
                'killmail_id' => $killmailData['killmail_id'] ?? null,
            ]);
            return null;
        }
    }

    private function fetchKillmailDetails(int $killmailId, string $killmailHash): ?array
    {
        try {
            $url = self::ESI_BASE . "/killmails/{$killmailId}/{$killmailHash}";

            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Failed to fetch killmail details", [
                'killmail_id' => $killmailId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching killmail details", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function isCharacterKill(array $killmailDetails, int $characterId): bool
    {
        // Character is a kill if they're in the attackers list
        $attackers = $killmailDetails['attackers'] ?? [];

        foreach ($attackers as $attacker) {
            if (($attacker['character_id'] ?? null) === $characterId) {
                return true;
            }
        }

        return false;
    }

    private function parseTimestamp(string $timestamp): \DateTime
    {
        return \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp) ?: now();
    }

    public function getKillmailsForCharacter(int $characterId): Collection
    {
        $character = CharacterInfo::find($characterId);

        if (!$character) {
            return collect();
        }

        return $this->collectKillsAndLosses(collect([$character]));
    }

    public function getLossesForCharacter(int $characterId): Collection
    {
        return $this->getKillmailsForCharacter($characterId)
            ->filter(fn($activity) => $activity->activity_type === 'pvp_loss');
    }
}

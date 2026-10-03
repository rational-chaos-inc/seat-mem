<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;

class MiningActivityService
{
    private bool $useManagerCore = false;

    public function __construct()
    {
        $this->useManagerCore = config('member-engagement.manager_core_integration', true)
            && class_exists(\ManagerCore\Topics::class);
    }

    public function collectMiningData(Collection $characters, ?Carbon $since = null): Collection
    {
        $activities = collect();

        if (!$characters || $characters->isEmpty()) {
            return $activities;
        }

        // If no since date provided, collect from last 30 days
        if (!$since) {
            $since = Carbon::now()->subDays(30);
        }

        // Try Manager-Core integration first (future enhancement)
        if ($this->useManagerCore) {
            $activities = $activities->merge($this->collectViaManagerCore($characters, $since));
        }

        // Also try direct query to Mining-Manager plugin tables
        $activities = $activities->merge($this->collectViaMiningManagerPlugin($characters, $since));

        return $activities;
    }

    private function collectViaManagerCore(Collection $characters, Carbon $since): Collection
    {
        try {
            // TODO: In future, subscribe to Mining-Manager events via Manager-Core EventBus
            // For now, fall back to direct query
            // Listen for mining.completed events
            // Convert events to Activity models
            Log::debug("Manager-Core integration not yet implemented, using direct query");
        } catch (\Exception $e) {
            Log::error("Failed to collect mining data via Manager-Core", [
                'error' => $e->getMessage(),
            ]);
        }

        return collect();
    }

    private function collectViaMiningManagerPlugin(Collection $characters, Carbon $since): Collection
    {
        $activities = collect();

        try {
            // Check if Mining-Manager plugin is installed
            if (!$this->miningLedgerModelExists()) {
                Log::debug("Mining-Manager plugin not installed, skipping mining data collection");
                return $activities;
            }

            $characterIds = $characters instanceof Collection
                ? $characters->pluck('character_id')->toArray()
                : $characters->toArray();

            if (empty($characterIds)) {
                return $activities;
            }

            // Use Mining-Manager's MiningLedger model
            $miningLedgerModel = 'MiningManager\Models\MiningLedger';

            $miningEntries = $miningLedgerModel::whereIn('character_id', $characterIds)
                ->where('date', '>=', $since->format('Y-m-d'))
                ->orderBy('date', 'desc')
                ->get();

            foreach ($miningEntries as $entry) {
                $activity = $this->parseMiningEntry($entry);
                if ($activity) {
                    $activities->push($activity);
                }
            }

            Log::info("Collected {$activities->count()} mining activities from Mining-Manager plugin");
        } catch (\Exception $e) {
            Log::error("Failed to collect mining data via Mining-Manager plugin", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $activities;
    }

    private function miningLedgerModelExists(): bool
    {
        return class_exists('MiningManager\Models\MiningLedger');
    }

    private function parseMiningEntry($entry): ?Activity
    {
        try {
            $totalValue = (float) ($entry->total_value ?? 0);
            $quantity = (float) ($entry->quantity ?? 0);

            if ($quantity <= 0 || $totalValue <= 0) {
                return null;
            }

            // Create unique source_id to prevent duplicates
            $sourceId = "mining_{$entry->character_id}_{$entry->date}_{$entry->type_id}_{$entry->solar_system_id}_" . ($entry->observer_id ?? 'personal');

            return Activity::make([
                'activity_timestamp' => Carbon::parse($entry->date)->startOfDay(),
                'character_id' => $entry->character_id,
                'corporation_id' => $entry->corporation_id ?? null,
                'alliance_id' => null,
                'activity_type' => 'mining',
                'source_id' => $sourceId,
                'metadata' => [
                    'type_id' => $entry->type_id,
                    'quantity' => $quantity,
                    'value' => $totalValue,
                    'ore_type' => $entry->ore_type ?? 'Unknown',
                    'ore_category' => $entry->ore_category ?? 'ore',
                    'solar_system_id' => $entry->solar_system_id,
                    'observer_id' => $entry->observer_id,
                    'tax_amount' => $entry->tax_amount ?? 0,
                    'is_moon_ore' => (bool) ($entry->is_moon_ore ?? false),
                    'is_ice' => (bool) ($entry->is_ice ?? false),
                    'is_gas' => (bool) ($entry->is_gas ?? false),
                    'is_abyssal' => (bool) ($entry->is_abyssal ?? false),
                    'is_triglavian' => (bool) ($entry->is_triglavian ?? false),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to parse mining entry", [
                'error' => $e->getMessage(),
                'character_id' => $entry->character_id ?? null,
                'date' => $entry->date ?? null,
            ]);
            return null;
        }
    }

    public function getMiningForCharacter(int $characterId, ?Carbon $since = null): Collection
    {
        if (!$since) {
            $since = Carbon::now()->subDays(30);
        }

        try {
            // Try Mining-Manager's MiningLedger model first
            if ($this->miningLedgerModelExists()) {
                $miningLedgerModel = 'MiningManager\Models\MiningLedger';

                $entries = $miningLedgerModel::where('character_id', $characterId)
                    ->where('date', '>=', $since->format('Y-m-d'))
                    ->orderBy('date', 'desc')
                    ->get();

                return $entries->map(fn($entry) => $this->parseMiningEntry($entry))
                    ->filter();
            }

            // Fallback to Activity table if Mining-Manager not available
            return Activity::where('activity_type', 'mining')
                ->where('character_id', $characterId)
                ->where('activity_timestamp', '>=', $since)
                ->orderBy('activity_timestamp', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to retrieve mining data for character", [
                'character_id' => $characterId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    public function getTotalMiningValueByCharacter(int $characterId, Carbon $start, Carbon $end): float
    {
        try {
            return Activity::where('activity_type', 'mining')
                ->where('character_id', $characterId)
                ->whereBetween('activity_timestamp', [$start, $end])
                ->get()
                ->sum(fn($activity) => $activity->getValueFromMetadata() ?? 0);
        } catch (\Exception $e) {
            Log::error("Failed to calculate mining total", [
                'character_id' => $characterId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}

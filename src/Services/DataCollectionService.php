<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;
use Seat\Eveapi\Models\Industry\CharacterMining;
use Seat\Eveapi\Models\Wallet\CorporationWalletJournal;

class DataCollectionService
{
    public function collectAll(?Carbon $since = null): array
    {
        $results = [
            'mining' => $this->collectMiningData($since),
            'kills' => $this->collectKillData($since),
            'losses' => $this->collectLossData($since),
            'tax' => $this->collectTaxData($since),
        ];

        return $results;
    }

    private function collectMiningData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $miningEntries = CharacterMining::where('date', '>=', $since->toDateString())
                ->orderBy('date', 'desc')
                ->get();

            foreach ($miningEntries as $entry) {
                $sourceId = "mining_{$entry->character_id}_{$entry->date}_{$entry->type_id}";

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'mining',
                        'character_id' => $entry->character_id,
                        'activity_timestamp' => Carbon::parse($entry->date)->startOfDay(),
                        'metadata' => [
                            'quantity' => $entry->quantity,
                            'type_id' => $entry->type_id,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} mining activities");
        } catch (\Exception $e) {
            Log::error("Error collecting mining data", ['error' => $e->getMessage()]);
        }

        return $count;
    }

    private function collectKillData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $kills = DB::table('killmail_details')
                ->join('killmail_attackers', 'killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id')
                ->join('killmail_victims', 'killmail_details.killmail_id', '=', 'killmail_victims.killmail_id')
                ->where('killmail_details.killmail_time', '>=', $since)
                ->select(
                    'killmail_details.killmail_id',
                    'killmail_details.killmail_time',
                    'killmail_attackers.character_id as attacker_character_id',
                    'killmail_victims.character_id as victim_character_id',
                    'killmail_victims.ship_type_id'
                )
                ->orderBy('killmail_details.killmail_time', 'desc')
                ->get();

            foreach ($kills as $kill) {
                if (!$kill->attacker_character_id) {
                    continue;
                }

                $sourceId = "kill_{$kill->killmail_id}_{$kill->attacker_character_id}";

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'pvp_kill',
                        'character_id' => $kill->attacker_character_id,
                        'activity_timestamp' => $kill->killmail_time,
                        'metadata' => [
                            'killmail_id' => $kill->killmail_id,
                            'victim_character_id' => $kill->victim_character_id,
                            'ship_type_id' => $kill->ship_type_id,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} kill activities");
        } catch (\Exception $e) {
            Log::error("Error collecting kill data", ['error' => $e->getMessage()]);
        }

        return $count;
    }

    private function collectLossData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $losses = DB::table('killmail_details')
                ->join('killmail_victims', 'killmail_details.killmail_id', '=', 'killmail_victims.killmail_id')
                ->join('killmail_attackers', 'killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id', 'left')
                ->where('killmail_details.killmail_time', '>=', $since)
                ->select(
                    'killmail_details.killmail_id',
                    'killmail_details.killmail_time',
                    'killmail_victims.character_id as victim_character_id',
                    'killmail_victims.ship_type_id',
                    'killmail_attackers.character_id as final_blow_by'
                )
                ->orderBy('killmail_details.killmail_time', 'desc')
                ->get();

            foreach ($losses as $loss) {
                if (!$loss->victim_character_id) {
                    continue;
                }

                $sourceId = "loss_{$loss->killmail_id}_{$loss->victim_character_id}";

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'pvp_loss',
                        'character_id' => $loss->victim_character_id,
                        'activity_timestamp' => $loss->killmail_time,
                        'metadata' => [
                            'killmail_id' => $loss->killmail_id,
                            'final_blow_by' => $loss->final_blow_by,
                            'ship_type_id' => $loss->ship_type_id,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} loss activities");
        } catch (\Exception $e) {
            Log::error("Error collecting loss data", ['error' => $e->getMessage()]);
        }

        return $count;
    }

    private function collectTaxData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $entries = CorporationWalletJournal::where('date', '>=', $since)
                ->orderBy('date', 'desc')
                ->get();

            foreach ($entries as $entry) {
                $sourceId = "wallet_{$entry->id}";

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'tax_wallet',
                        'character_id' => 0,
                        'corporation_id' => $entry->corporation_id,
                        'activity_timestamp' => $entry->date,
                        'metadata' => [
                            'amount' => abs($entry->amount ?? 0),
                            'ref_type' => $entry->ref_type,
                            'description' => $entry->description,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} tax/bounty activities");
        } catch (\Exception $e) {
            Log::error("Error collecting tax data", ['error' => $e->getMessage()]);
        }

        return $count;
    }
}

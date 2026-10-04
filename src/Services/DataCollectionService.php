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
            'pve_bounty_tax' => $this->collectPveBountyTaxData($since),
            'industry_tax' => $this->collectIndustryTaxData($since),
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

            // Total attackers per killmail, used to determine fleet size for Fleet Participation
            $attackerCounts = DB::table('killmail_attackers')
                ->select('killmail_id', DB::raw('COUNT(*) as attacker_count'))
                ->groupBy('killmail_id')
                ->pluck('attacker_count', 'killmail_id');

            $kills = DB::table('killmail_details')
                ->join('killmail_attackers', 'killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id')
                ->join('killmail_victims', 'killmail_details.killmail_id', '=', 'killmail_victims.killmail_id')
                ->where('killmail_details.killmail_time', '>=', $since)
                ->select(
                    'killmail_details.killmail_id',
                    'killmail_details.killmail_time',
                    'killmail_attackers.character_id as attacker_character_id',
                    'killmail_attackers.corporation_id as attacker_corporation_id',
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
                        'corporation_id' => $kill->attacker_corporation_id,
                        'activity_timestamp' => $kill->killmail_time,
                        'metadata' => [
                            'killmail_id' => $kill->killmail_id,
                            'victim_character_id' => $kill->victim_character_id,
                            'ship_type_id' => $kill->ship_type_id,
                            'attacker_count' => $attackerCounts[$kill->killmail_id] ?? 1,
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
                ->leftJoin('killmail_attackers', function ($join) {
                    $join->on('killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id')
                        ->where('killmail_attackers.final_blow', '=', 1);
                })
                ->where('killmail_details.killmail_time', '>=', $since)
                ->select(
                    'killmail_details.killmail_id',
                    'killmail_details.killmail_time',
                    'killmail_victims.character_id as victim_character_id',
                    'killmail_victims.corporation_id as victim_corporation_id',
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
                        'corporation_id' => $loss->victim_corporation_id,
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

    /**
     * Wallet ref_types that pay out to an individual character (second_party_id),
     * as opposed to corp-to-corp transactions (industry tax, market escrow, etc.)
     * which have no individual member to attribute them to.
     */
    private const CHARACTER_PAYOUT_REF_TYPES = ['bounty_prizes', 'daily_goal_payouts'];

    private function collectPveBountyTaxData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $entries = CorporationWalletJournal::where('date', '>=', $since)
                ->whereIn('ref_type', self::CHARACTER_PAYOUT_REF_TYPES)
                ->orderBy('date', 'desc')
                ->get();

            foreach ($entries as $entry) {
                // For these ref_types, first_party_id is the paying NPC entity
                // (e.g. CONCORD bounty office) and second_party_id is the
                // character who received the payout.
                $characterId = $entry->second_party_id;
                if (!$characterId) {
                    continue;
                }

                $sourceId = "wallet_{$entry->id}";

                // bounty_prizes descriptions read "<Character Name> got bounty
                // prizes for killing pirates in <system>" - extract the name as
                // a display fallback for characters SeAT hasn't synced yet.
                $characterName = null;
                if ($entry->description && preg_match('/^(.+?)\s+got\s+/', $entry->description, $matches)) {
                    $characterName = $matches[1];
                }

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'pve_bounty_tax',
                        'character_id' => $characterId,
                        'corporation_id' => $entry->corporation_id,
                        'activity_timestamp' => $entry->date,
                        'metadata' => [
                            'amount' => abs($entry->amount ?? 0),
                            'ref_type' => $entry->ref_type,
                            'description' => $entry->description,
                            'character_name' => $characterName,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} PvE bounty/tax activities");
        } catch (\Exception $e) {
            Log::error("Error collecting PvE bounty/tax data", ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * Industry facility tax entries (ref_type industry_job_tax) are between two
     * corporations (first_party_id/second_party_id are both corporation IDs, not
     * characters), so there's no character to attribute directly from the wallet
     * entry itself. The description includes the originating Job ID though
     * ("Industry facility tax between X and Y (Job ID: 123456)"), which we cross-
     * reference against corporation_industry_jobs.installer_id to find the
     * character who actually ran the job. SeAT's local job cache only retains a
     * limited window of jobs, so older tax entries may not resolve - those are
     * skipped rather than attributed to the wrong (or no) character.
     */
    private function collectIndustryTaxData(?Carbon $since = null): int
    {
        $count = 0;

        try {
            if (!$since) {
                $since = Carbon::now()->subDays(90);
            }

            $entries = CorporationWalletJournal::where('date', '>=', $since)
                ->where('ref_type', 'industry_job_tax')
                ->orderBy('date', 'desc')
                ->get();

            $jobIds = [];
            foreach ($entries as $entry) {
                if ($entry->description && preg_match('/Job ID:\s*(\d+)/', $entry->description, $matches)) {
                    $jobIds[] = (int) $matches[1];
                }
            }

            $installerByJobId = DB::table('corporation_industry_jobs')
                ->whereIn('job_id', array_unique($jobIds))
                ->pluck('installer_id', 'job_id');

            foreach ($entries as $entry) {
                if (!$entry->description || !preg_match('/Job ID:\s*(\d+)/', $entry->description, $matches)) {
                    continue;
                }

                $jobId = (int) $matches[1];
                $characterId = $installerByJobId[$jobId] ?? null;
                if (!$characterId) {
                    continue;
                }

                $sourceId = "wallet_{$entry->id}";

                Activity::updateOrCreate(
                    ['source_id' => $sourceId],
                    [
                        'activity_type' => 'industry_tax',
                        'character_id' => $characterId,
                        'corporation_id' => $entry->corporation_id,
                        'activity_timestamp' => $entry->date,
                        'metadata' => [
                            'amount' => abs($entry->amount ?? 0),
                            'ref_type' => $entry->ref_type,
                            'description' => $entry->description,
                            'job_id' => $jobId,
                        ],
                    ]
                );
                $count++;
            }

            Log::info("Collected {$count} industry tax activities");
        } catch (\Exception $e) {
            Log::error("Error collecting industry tax data", ['error' => $e->getMessage()]);
        }

        return $count;
    }
}

<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use RCI\MemberEngagement\Models\Activity;
use Carbon\Carbon;

class GenerateTestDataCommand extends Command
{
    protected $signature = 'member-engagement:test-data {count=50}';

    protected $description = 'Generate test activity data for development';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $types = ['mining', 'pvp_kill', 'pvp_loss', 'tax_wallet'];
        $characterIds = [227993904, 227993905, 227993906];
        $corporationId = 98765432;

        $this->info("Generating {$count} test activities...");

        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $characterId = $characterIds[array_rand($characterIds)];
            $timestamp = Carbon::now()->subDays(random_int(0, 30));

            Activity::create([
                'activity_type' => $type,
                'character_id' => $characterId,
                'corporation_id' => $corporationId,
                'activity_timestamp' => $timestamp,
                'source_id' => "test_{$i}_{$type}",
                'metadata' => $this->generateMetadata($type),
            ]);
        }

        $this->info("Created {$count} test activities");
        return self::SUCCESS;
    }

    private function generateMetadata(string $type): array
    {
        return match ($type) {
            'mining' => [
                'quantity' => random_int(100, 1000),
                'value' => random_int(100000, 1000000),
                'ore_type' => 'Veldspar',
            ],
            'pvp_kill' => [
                'victim_name' => 'Test Victim',
                'ship_type' => 'Rifter',
                'value' => random_int(500000, 5000000),
            ],
            'pvp_loss' => [
                'killer_name' => 'Test Killer',
                'ship_type' => 'Rifter',
                'loss_value' => random_int(500000, 5000000),
            ],
            'tax_wallet' => [
                'amount' => random_int(1000000, 10000000),
                'reason' => 'NPC Bounty',
            ],
            default => [],
        };
    }
}

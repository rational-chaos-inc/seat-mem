<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Jobs\CollectActivitiesJob;
use Seat\Eveapi\Models\Character\CharacterInfo;

class CollectActivitiesCommand extends Command
{
    protected $signature = 'member-engagement:collect-activities {corporation_id?}';

    protected $description = 'Collect activity data (kills/losses, mining, tax wallet) for corporation members';

    public function handle(): int
    {
        Log::info("Member Engagement Module activity collection started");

        try {
            $corporationId = $this->argument('corporation_id');

            if ($corporationId) {
                // Collect for specific corporation
                $this->collectForCorporation((int) $corporationId);
            } else {
                // Collect for all corporations with members
                $this->collectForAllCorporations();
            }

            Log::info("Member Engagement Module activity collection completed");
            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Member Engagement Module activity collection failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Collection failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function collectForCorporation(int $corporationId): void
    {
        $this->info("Collecting activities for corporation {$corporationId}");

        // Dispatch job to queue
        CollectActivitiesJob::dispatch($corporationId);

        $this->info("Activity collection job dispatched for corporation {$corporationId}");
    }

    private function collectForAllCorporations(): void
    {
        // Get all unique corporations with members
        $corporations = CharacterInfo::whereHas('affiliation', function ($q) {
                $q->whereNotNull('corporation_id');
            })
            ->distinct()
            ->with('affiliation')
            ->get()
            ->pluck('affiliation.corporation_id')
            ->unique();

        if ($corporations->isEmpty()) {
            $this->warn("No corporations found with members");
            return;
        }

        $this->info("Collecting activities for {$corporations->count()} corporations");

        foreach ($corporations as $corporationId) {
            CollectActivitiesJob::dispatch($corporationId);
        }

        $this->info("Activity collection jobs dispatched for all corporations");
    }
}

<?php

namespace RCI\MemberEngagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Services\ActivityCollectionService;

class CollectActivitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public int $backoff = 60; // seconds

    public function __construct(
        private int $corporationId,
    ) {
    }

    public function handle(ActivityCollectionService $collectionService): void
    {
        Log::info("CollectActivitiesJob starting for corporation {$this->corporationId}");

        try {
            $collectionService->collectForCorporation($this->corporationId);
            Log::info("CollectActivitiesJob completed for corporation {$this->corporationId}");
        } catch (\Exception $e) {
            Log::error("CollectActivitiesJob failed for corporation {$this->corporationId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CollectActivitiesJob permanently failed for corporation {$this->corporationId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\ActivityAggregationCache;

class CacheAggregationsCommand extends Command
{
    protected $signature = 'member-engagement:cache-aggregations
                            {action : clear|warmup|stats}
                            {--user-id= : Filter by user ID}
                            {--period= : Filter by aggregation period (day|week|month|quarter|year)}
                            {--type= : Filter by activity type}';

    protected $description = 'Manage activity aggregation cache (clear, warmup, or show stats)';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'clear' => $this->clearCache(),
            'warmup' => $this->warmupCache(),
            'stats' => $this->showStats(),
            default => $this->fail("Unknown action: {$action}"),
        };
    }

    private function clearCache(): int
    {
        try {
            $query = ActivityAggregationCache::query();

            if ($this->option('user-id')) {
                $query->where('user_id', $this->option('user-id'));
            }

            if ($this->option('period')) {
                $query->where('aggregation_period', $this->option('period'));
            }

            if ($this->option('type')) {
                $query->where('activity_type', $this->option('type'));
            }

            $count = $query->count();
            $query->delete();

            Log::info("Cleared {$count} aggregation cache entries");
            $this->info("Cleared {$count} aggregation cache entries");

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Failed to clear cache", ['error' => $e->getMessage()]);
            $this->error("Failed to clear cache: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function warmupCache(): int
    {
        $this->info("Cache warmup not yet implemented - aggregations are computed on-demand");
        $this->line("To enable caching, set MEMBER_ENGAGEMENT_CACHE_TTL in .env");

        return self::SUCCESS;
    }

    private function showStats(): int
    {
        try {
            $totalCount = ActivityAggregationCache::count();
            $oldestEntry = ActivityAggregationCache::orderBy('created_at')->first();
            $newestEntry = ActivityAggregationCache::orderBy('created_at', 'desc')->first();
            $byPeriod = ActivityAggregationCache::selectRaw('aggregation_period, COUNT(*) as count')
                ->groupBy('aggregation_period')
                ->pluck('count', 'aggregation_period')
                ->toArray();

            $this->info("=== Aggregation Cache Statistics ===");
            $this->line("Total cached aggregations: {$totalCount}");
            $this->line("Oldest entry: {$oldestEntry?->created_at->format('Y-m-d H:i:s')}");
            $this->line("Newest entry: {$newestEntry?->created_at->format('Y-m-d H:i:s')}");
            $this->line("\nBy period:");
            foreach ($byPeriod as $period => $count) {
                $this->line("  {$period}: {$count}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error("Failed to show cache stats", ['error' => $e->getMessage()]);
            $this->error("Failed to show stats: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}

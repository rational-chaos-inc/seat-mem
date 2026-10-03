<?php

namespace RCI\MemberEngagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Services\AggregationService;

class AggregationApiController
{
    public function __construct(
        private AggregationService $aggregationService,
    ) {
    }

    public function userAggregation(Request $request)
    {
        try {
            $user = Auth::user();
            $timeWindow = $request->query('window', 'month');
            $type = $request->query('type');

            // Validate time window
            if (!in_array($timeWindow, ['day', 'week', 'month', 'quarter', 'year'])) {
                return response()->json(['error' => 'Invalid time window'], 422);
            }

            // Validate type if provided
            if ($type && !in_array($type, config('member-engagement.activity_types', []))) {
                return response()->json(['error' => 'Invalid activity type'], 422);
            }

            $aggregation = $this->aggregationService->aggregateForUser(
                $user->id,
                $timeWindow,
                $type
            );

            Log::debug("User aggregation API called", [
                'user_id' => $user->id,
                'window' => $timeWindow,
            ]);

            return response()->json($aggregation);
        } catch (\Exception $e) {
            Log::error("User aggregation API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch aggregation'], 500);
        }
    }

    public function userByType(Request $request)
    {
        try {
            $user = Auth::user();
            $timeWindow = $request->query('window', 'month');

            // Validate time window
            if (!in_array($timeWindow, ['day', 'week', 'month', 'quarter', 'year'])) {
                return response()->json(['error' => 'Invalid time window'], 422);
            }

            $aggregations = $this->aggregationService->aggregateByActivityType(
                $user->id,
                $timeWindow
            );

            return response()->json($aggregations);
        } catch (\Exception $e) {
            Log::error("User by-type aggregation API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch aggregations'], 500);
        }
    }

    public function characterAggregation(Request $request, int $characterId)
    {
        try {
            $user = Auth::user();
            $timeWindow = $request->query('window', 'month');
            $type = $request->query('type');

            // Verify user owns this character
            if (!method_exists($user, 'characters')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $character = $user->characters()
                ->where('character_id', $characterId)
                ->first();

            if (!$character) {
                return response()->json(['error' => 'Character not found'], 404);
            }

            // Validate time window
            if (!in_array($timeWindow, ['day', 'week', 'month', 'quarter', 'year'])) {
                return response()->json(['error' => 'Invalid time window'], 422);
            }

            $aggregation = $this->aggregationService->aggregateForCharacter(
                $characterId,
                $timeWindow,
                $type
            );

            return response()->json($aggregation);
        } catch (\Exception $e) {
            Log::error("Character aggregation API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch aggregation'], 500);
        }
    }

    public function leagueTable(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate permission
            if (!$user->can('view_all_activities')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $timeWindow = $request->query('window', 'month');
            $type = $request->query('type', 'mining');
            $limit = min((int) $request->query('limit', 50), 500);

            // Get user's corporation
            if (!method_exists($user, 'characters')) {
                return response()->json(['error' => 'No linked characters'], 403);
            }

            $corporationId = $user->characters()
                ->whereNotNull('corporation_id')
                ->first()
                ?->corporation_id;

            if (!$corporationId) {
                return response()->json(['error' => 'No corporation found'], 403);
            }

            // Validate time window and type
            if (!in_array($timeWindow, ['day', 'week', 'month', 'quarter', 'year'])) {
                return response()->json(['error' => 'Invalid time window'], 422);
            }

            if (!in_array($type, config('member-engagement.activity_types', []))) {
                return response()->json(['error' => 'Invalid activity type'], 422);
            }

            $leagueTable = $this->aggregationService->getLeagueTable(
                $corporationId,
                $timeWindow,
                $type,
                $limit
            );

            return response()->json([
                'data' => $leagueTable,
                'meta' => [
                    'window' => $timeWindow,
                    'type' => $type,
                    'count' => count($leagueTable),
                    'limit' => $limit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("League table API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch league table'], 500);
        }
    }

    public function corporationAggregation(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate permission
            if (!$user->can('view_all_activities')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $timeWindow = $request->query('window', 'month');

            // Get user's corporation
            if (!method_exists($user, 'characters')) {
                return response()->json(['error' => 'No linked characters'], 403);
            }

            $corporationId = $user->characters()
                ->whereNotNull('corporation_id')
                ->first()
                ?->corporation_id;

            if (!$corporationId) {
                return response()->json(['error' => 'No corporation found'], 403);
            }

            // Validate time window
            if (!in_array($timeWindow, ['day', 'week', 'month', 'quarter', 'year'])) {
                return response()->json(['error' => 'Invalid time window'], 422);
            }

            $aggregations = $this->aggregationService->aggregateForCorporation(
                $corporationId,
                $timeWindow
            );

            return response()->json([
                'data' => $aggregations,
                'meta' => [
                    'window' => $timeWindow,
                    'corporation_id' => $corporationId,
                    'member_count' => count($aggregations),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Corporation aggregation API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch aggregations'], 500);
        }
    }
}

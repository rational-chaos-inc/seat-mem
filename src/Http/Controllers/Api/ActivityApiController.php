<?php

namespace RCI\MemberEngagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RCI\MemberEngagement\Models\Activity;

class ActivityApiController
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = min((int) $request->query('limit', 50), 500);
            $offset = (int) $request->query('offset', 0);
            $type = $request->query('type');
            $characterId = $request->query('character_id');

            // Get user's character IDs
            if (!method_exists($user, 'characters')) {
                return response()->json(['error' => 'No linked characters'], 403);
            }

            $characterIds = $user->characters()->pluck('character_id')->toArray();

            if (empty($characterIds)) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $query = Activity::whereIn('character_id', $characterIds);

            // Filter by specific character if requested
            if ($characterId && in_array($characterId, $characterIds)) {
                $query->where('character_id', $characterId);
            }

            // Filter by type if requested
            if ($type && in_array($type, config('member-engagement.activity_types', []))) {
                $query->where('activity_type', $type);
            }

            $total = $query->count();
            $activities = $query->orderBy('activity_timestamp', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn($a) => $this->formatActivity($a));

            Log::debug("Activity API called", [
                'user_id' => $user->id,
                'limit' => $limit,
                'type' => $type,
            ]);

            return response()->json([
                'data' => $activities,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Activity API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch activities'], 500);
        }
    }

    public function show(int $activityId)
    {
        try {
            $user = Auth::user();

            // Get user's character IDs
            if (!method_exists($user, 'characters')) {
                return response()->json(['error' => 'No linked characters'], 403);
            }

            $characterIds = $user->characters()->pluck('character_id')->toArray();

            $activity = Activity::where('id', $activityId)
                ->whereIn('character_id', $characterIds)
                ->first();

            if (!$activity) {
                return response()->json(['error' => 'Activity not found'], 404);
            }

            return response()->json($this->formatActivity($activity));
        } catch (\Exception $e) {
            Log::error("Activity detail API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch activity'], 500);
        }
    }

    private function formatActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'activity_type' => $activity->activity_type,
            'character_id' => $activity->character_id,
            'corporation_id' => $activity->corporation_id,
            'alliance_id' => $activity->alliance_id,
            'timestamp' => $activity->activity_timestamp?->toIso8601String(),
            'value' => $activity->getValueFromMetadata(),
            'quantity' => $activity->getQuantityFromMetadata(),
            'metadata' => $activity->metadata,
            'created_at' => $activity->created_at->toIso8601String(),
            'updated_at' => $activity->updated_at->toIso8601String(),
        ];
    }
}

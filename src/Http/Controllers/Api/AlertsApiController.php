<?php

namespace RCI\MemberEngagement\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RCI\MemberEngagement\Models\ActivityAlert;
use RCI\MemberEngagement\Services\AlertService;

class AlertsApiController
{
    public function __construct(
        private AlertService $alertService,
    ) {
    }

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->query('type');

            $query = ActivityAlert::forUser($user->id);

            if ($type) {
                $query->ofType($type);
            }

            $alerts = $query->orderBy('created_at', 'desc')->get()
                ->map(fn($a) => $this->formatAlert($a));

            return response()->json([
                'data' => $alerts,
                'count' => $alerts->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Alerts list API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch alerts'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate input
            $validator = Validator::make($request->all(), [
                'alert_type' => 'required|in:activity_threshold,unusual_activity,member_alert',
                'conditions' => 'required|array',
                'conditions.activity_types' => 'array',
                'conditions.value_threshold' => 'nullable|numeric|min:0',
                'conditions.time_window' => 'nullable|in:day,week,month,quarter,year',
                'notification_method' => 'required|in:in_app,email,webhook',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create alert
            $alert = ActivityAlert::create([
                'user_id' => $user->id,
                'alert_type' => $request->input('alert_type'),
                'conditions' => $request->input('conditions'),
                'notification_method' => $request->input('notification_method'),
                'is_active' => true,
            ]);

            Log::info("Alert created", [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
            ]);

            return response()->json($this->formatAlert($alert), 201);
        } catch (\Exception $e) {
            Log::error("Alert creation API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create alert'], 500);
        }
    }

    public function show(int $alertId)
    {
        try {
            $user = Auth::user();

            $alert = ActivityAlert::where('id', $alertId)
                ->where('user_id', $user->id)
                ->first();

            if (!$alert) {
                return response()->json(['error' => 'Alert not found'], 404);
            }

            return response()->json($this->formatAlert($alert));
        } catch (\Exception $e) {
            Log::error("Alert detail API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch alert'], 500);
        }
    }

    public function update(Request $request, int $alertId)
    {
        try {
            $user = Auth::user();

            $alert = ActivityAlert::where('id', $alertId)
                ->where('user_id', $user->id)
                ->first();

            if (!$alert) {
                return response()->json(['error' => 'Alert not found'], 404);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'conditions' => 'array',
                'notification_method' => 'in:in_app,email,webhook',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $alert->update($request->only(['conditions', 'notification_method', 'is_active']));

            Log::info("Alert updated", [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
            ]);

            return response()->json($this->formatAlert($alert));
        } catch (\Exception $e) {
            Log::error("Alert update API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update alert'], 500);
        }
    }

    public function destroy(int $alertId)
    {
        try {
            $user = Auth::user();

            $alert = ActivityAlert::where('id', $alertId)
                ->where('user_id', $user->id)
                ->first();

            if (!$alert) {
                return response()->json(['error' => 'Alert not found'], 404);
            }

            $alert->delete();

            Log::info("Alert deleted", [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
            ]);

            return response()->json(['message' => 'Alert deleted'], 200);
        } catch (\Exception $e) {
            Log::error("Alert deletion API error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete alert'], 500);
        }
    }

    public function testAlert(int $alertId)
    {
        try {
            $user = Auth::user();

            $alert = ActivityAlert::where('id', $alertId)
                ->where('user_id', $user->id)
                ->first();

            if (!$alert) {
                return response()->json(['error' => 'Alert not found'], 404);
            }

            // Send test notification
            $this->alertService->sendTestNotification($alert);

            Log::info("Test alert sent", [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
            ]);

            return response()->json(['message' => 'Test notification sent']);
        } catch (\Exception $e) {
            Log::error("Test alert error", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to send test notification'], 500);
        }
    }

    private function formatAlert(ActivityAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->alert_type,
            'conditions' => $alert->conditions,
            'notification_method' => $alert->notification_method,
            'is_active' => $alert->is_active,
            'last_triggered_at' => $alert->last_triggered_at?->toIso8601String(),
            'created_at' => $alert->created_at->toIso8601String(),
            'updated_at' => $alert->updated_at->toIso8601String(),
        ];
    }
}

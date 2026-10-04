<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RCI\MemberEngagement\Models\Activity;
use Carbon\Carbon;

class DashboardController
{
    public function member(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_own_activities')) {
            abort(403, 'Unauthorized');
        }

        $timeWindow = $request->query('window', 'month');
        $days = config('member-engagement.time_windows.' . $timeWindow, 30);

        $activities = Activity::where('activity_timestamp', '>=', Carbon::now()->subDays($days))
            ->orderBy('activity_timestamp', 'desc')
            ->limit(100)
            ->get();

        // Batch load character names
        $charIds = $activities->pluck('character_id')->filter()->unique();

        $charNames = \DB::table('character_infos')
            ->whereIn('character_id', $charIds)
            ->pluck('name', 'character_id');

        foreach ($activities as $activity) {
            $activity->character_name = $charNames[$activity->character_id]
                ?? $activity->metadata['character_name']
                ?? null;
        }

        return view('member-engagement::dashboard.member', [
            'activities' => $activities,
            'timeWindow' => $timeWindow,
        ]);
    }
}

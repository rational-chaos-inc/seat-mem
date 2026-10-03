<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RCI\MemberEngagement\Models\Activity;
use Carbon\Carbon;

class DirectorController
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }

        $timeWindow = $request->query('window', 'month');
        $days = config('member-engagement.time_windows.' . $timeWindow, 30);

        $activities = Activity::where('activity_timestamp', '>=', Carbon::now()->subDays($days))
            ->orderBy('activity_timestamp', 'desc')
            ->limit(100)
            ->get();

        // Batch load character and corporation names
        $charIds = $activities->pluck('character_id')->filter()->unique();
        $corpIds = $activities->pluck('corporation_id')->filter()->unique();

        $charNames = \DB::table('character_infos')
            ->whereIn('character_id', $charIds)
            ->pluck('name', 'character_id');

        $corpNames = \DB::table('corporation_infos')
            ->whereIn('corporation_id', $corpIds)
            ->pluck('name', 'corporation_id');

        foreach ($activities as $activity) {
            $activity->character_name = $charNames[$activity->character_id] ?? null;
            $activity->corporation_name = $corpNames[$activity->corporation_id] ?? null;
        }

        return view('member-engagement::dashboard.director', [
            'activities' => $activities,
            'timeWindow' => $timeWindow,
        ]);
    }
}

<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RCI\MemberEngagement\Models\Activity;
use RCI\MemberEngagement\Services\EntityResolutionService;
use Carbon\Carbon;

class DirectorController
{
    public function __construct(
        private EntityResolutionService $entityResolution,
    ) {
    }

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

        $this->entityResolution->attachDisplayNames($activities);

        return view('member-engagement::dashboard.director', [
            'activities' => $activities,
            'timeWindow' => $timeWindow,
        ]);
    }
}

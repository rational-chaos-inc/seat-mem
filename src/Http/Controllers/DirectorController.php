<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Services\NetWorthService;
use RCI\MemberEngagement\Services\RankingService;

class DirectorController
{
    public function __construct(
        private RankingService $rankingService,
        private NetWorthService $netWorthService,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }

        [$month, $isCurrentMonth] = $this->rankingService->resolveMonth($request->query('month'));

        $rankings = $this->rankingService->buildRankings($month, $isCurrentMonth);
        $heartbeat = $this->rankingService->buildHeartbeat($month, $isCurrentMonth);
        $netWorth = $this->netWorthService->buildNetWorth($month, $isCurrentMonth);

        $setting = MemberEngagementSetting::current();
        $homeRegionIds = $setting->home_regions ?? [];

        return view('member-engagement::dashboard.director', [
            'rankings' => $rankings,
            'heartbeat' => $heartbeat,
            'netWorth' => $netWorth,
            'netWorthTakenAt' => $this->netWorthService->snapshotTakenAt($month, $isCurrentMonth),
            'minimumHomePresence' => (int) ($setting->minimum_home_presence ?? 80),
            'homeRegionNames' => empty($homeRegionIds)
                ? []
                : DB::table('regions')->whereIn('region_id', $homeRegionIds)->pluck('name')->all(),
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'availableMonths' => $this->rankingService->availableMonths(),
        ]);
    }
}

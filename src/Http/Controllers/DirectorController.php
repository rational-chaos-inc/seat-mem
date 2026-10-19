<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RCI\MemberEngagement\Services\RankingService;

class DirectorController
{
    public function __construct(
        private RankingService $rankingService,
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

        return view('member-engagement::dashboard.director', [
            'rankings' => $rankings,
            'heartbeat' => $heartbeat,
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'availableMonths' => $this->rankingService->availableMonths(),
        ]);
    }
}

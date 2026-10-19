<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RCI\MemberEngagement\Services\AccountConsolidationService;
use RCI\MemberEngagement\Services\RankingService;

class DashboardController
{
    public function __construct(
        private RankingService $rankingService,
        private AccountConsolidationService $consolidation,
    ) {
    }

    public function member(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_own_activities')) {
            abort(403, 'Unauthorized');
        }

        [$month, $isCurrentMonth] = $this->rankingService->resolveMonth($request->query('month'));

        $characterIds = $this->consolidation->charactersForUser($user->id, $user->main_character_id);
        $stats = $this->rankingService->buildOwnStats($characterIds, $month, $isCurrentMonth);

        return view('member-engagement::dashboard.member', [
            'stats' => $stats,
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'availableMonths' => $this->rankingService->availableMonths(),
            'hasLinkedCharacters' => !empty($characterIds),
        ]);
    }
}

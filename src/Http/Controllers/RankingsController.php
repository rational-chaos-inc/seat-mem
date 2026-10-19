<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Services\RankingService;

class RankingsController
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

        $currentMonthStart = Carbon::now()->startOfMonth();

        $monthParam = $request->query('month');
        $month = $monthParam ? Carbon::parse($monthParam . '-01')->startOfMonth() : $currentMonthStart->copy();

        // Never allow navigating into the future.
        if ($month->greaterThan($currentMonthStart)) {
            $month = $currentMonthStart->copy();
        }

        $isCurrentMonth = $month->equalTo($currentMonthStart);

        $rankings = $this->rankingService->buildRankings($month, $isCurrentMonth);

        $availableMonths = $this->buildAvailableMonths($currentMonthStart);

        return view('member-engagement::dashboard.rankings', [
            'rankings' => $rankings,
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'availableMonths' => $availableMonths,
        ]);
    }

    private function buildAvailableMonths(Carbon $currentMonthStart): array
    {
        $earliestDate = DB::table('member_engagement_daily_stats')->min('date');

        if (!$earliestDate) {
            return [$currentMonthStart->copy()];
        }

        $months = [];
        $cursor = Carbon::parse($earliestDate)->startOfMonth();

        while ($cursor->lte($currentMonthStart)) {
            $months[] = $cursor->copy();
            $cursor->addMonthNoOverflow();
        }

        return array_reverse($months);
    }
}

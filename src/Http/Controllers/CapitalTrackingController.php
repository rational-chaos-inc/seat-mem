<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Services\AccountConsolidationService;
use RCI\MemberEngagement\Services\CapitalHistoryService;
use RCI\MemberEngagement\Services\CapitalTrackingService;

class CapitalTrackingController
{
    public function __construct(
        private CapitalTrackingService $capitals,
        private CapitalHistoryService $history,
        private AccountConsolidationService $consolidation,
    ) {
    }

    public function index(Request $request)
    {
        if (!Auth::user()->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }

        $primaryCorporationId = $this->consolidation->primaryCorporationId();
        $data = $this->capitals->cached();

        return view('member-engagement::dashboard.capitals', [
            'types' => $data['types'],
            'accounts' => $data['accounts'],
            'generatedAt' => $data['generated_at'],
            'missingGroups' => $data['missing_groups'],
            'classSummary' => $this->history->classSummary($data['accounts']),
            'fleetHistory' => $this->history->stackedSeries(),
            'bands' => CapitalHistoryService::BANDS,
            'groups' => $this->capitals->groups(),
            'primaryCorporationName' => $primaryCorporationId === null
                ? null
                : DB::table('corporation_infos')->where('corporation_id', $primaryCorporationId)->value('name'),
        ]);
    }
}

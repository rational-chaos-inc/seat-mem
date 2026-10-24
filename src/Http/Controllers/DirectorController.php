<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Services\AccountConsolidationService;
use RCI\MemberEngagement\Services\NetWorthService;
use RCI\MemberEngagement\Services\RankingService;

class DirectorController
{
    public function __construct(
        private RankingService $rankingService,
        private NetWorthService $netWorthService,
        private AccountConsolidationService $consolidation,
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
        $deploymentRegionIds = $setting->deployment_regions ?? [];

        $primaryCorporationId = $this->consolidation->primaryCorporationId();

        return view('member-engagement::dashboard.director', [
            'primaryCorporationName' => $primaryCorporationId === null
                ? null
                : DB::table('corporation_infos')->where('corporation_id', $primaryCorporationId)->value('name'),
            'rankings' => $rankings,
            'heartbeat' => $heartbeat,
            'netWorth' => $netWorth,
            'netWorthTakenAt' => $this->netWorthService->snapshotTakenAt($month, $isCurrentMonth),
            'minimumHomePresence' => (int) ($setting->minimum_home_presence ?? 80),
            'homeRegionNames' => $this->regionNames($homeRegionIds),
            'deploymentRegionNames' => $this->regionNames($deploymentRegionIds),
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'availableMonths' => $this->rankingService->availableMonths(),
        ]);
    }

    /** @return string[] */
    private function regionNames(array $regionIds): array
    {
        return empty($regionIds)
            ? []
            : DB::table('regions')->whereIn('region_id', $regionIds)->orderBy('name')->pluck('name')->all();
    }

    /**
     * The assets behind one of an account's slices. Loaded on
     * demand rather than embedded in every page - small here but it scales
     * with the number of registered characters.
     */
    public function bucketAssets(Request $request, int $characterId, string $bucket)
    {
        $user = Auth::user();

        if (!$user->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }

        $allowed = [
            NetWorthService::UNKNOWN_REGION,
            NetWorthService::CUSTOMS_OFFICE,
            NetWorthService::ASSET_SAFETY,
        ];

        // Either one of the named buckets, or a region id.
        if (!in_array($bucket, $allowed, true) && !ctype_digit($bucket)) {
            abort(404, 'Unknown asset bucket');
        }

        $group = $this->consolidation->allAccountGroups()[$characterId] ?? null;

        if ($group === null) {
            abort(404, 'Not a registered main character');
        }

        return response()->json([
            'locations' => $this->netWorthService->bucketAssets($group, $bucket),
        ]);
    }
}

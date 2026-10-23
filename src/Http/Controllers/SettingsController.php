<?php

namespace RCI\MemberEngagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\MemberEngagementSetting;

class SettingsController
{
    public function index(Request $request)
    {
        $this->authorize();

        $setting = MemberEngagementSetting::current();
        $homeRegionIds = array_map('intval', $setting->home_regions ?? []);
        $deploymentRegionIds = array_map('intval', $setting->deployment_regions ?? []);

        $regions = DB::table('regions')->orderBy('name')->get(['region_id', 'name']);
        $claimed = array_merge($homeRegionIds, $deploymentRegionIds);

        return view('member-engagement::settings.index', [
            'setting' => $setting,
            'homeRegions' => $regions->whereIn('region_id', $homeRegionIds)->values(),
            'deploymentRegions' => $regions->whereIn('region_id', $deploymentRegionIds)->values(),
            // A region can only be in one list, so the picker offers
            // whatever neither list has claimed.
            'availableRegions' => $regions->whereNotIn('region_id', $claimed)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize();

        $validated = $request->validate([
            'login_visibility' => 'required|in:directors,members,both',
            'mining_visibility' => 'required|in:directors,members,both',
            'pve_bounty_visibility' => 'required|in:directors,members,both',
            'industry_tax_visibility' => 'required|in:directors,members,both',
            'pvp_visibility' => 'required|in:directors,members,both',
            'fleet_participation_visibility' => 'required|in:directors,members,both',
            'mining_weight' => 'required|numeric|min:0|max:10',
            'pve_bounty_weight' => 'required|numeric|min:0|max:10',
            'industry_tax_weight' => 'required|numeric|min:0|max:10',
            'pvp_weight' => 'required|numeric|min:0|max:10',
            'fleet_participation_weight' => 'required|numeric|min:0|max:10',
            'fleet_participation_min_size' => 'required|integer|min:1|max:100',
            'minimum_home_presence' => 'required|integer|min:0|max:100',
        ]);

        $setting = MemberEngagementSetting::current();
        $setting->update([
            'login_visibility' => $validated['login_visibility'],
            'mining_visibility' => $validated['mining_visibility'],
            'pve_bounty_visibility' => $validated['pve_bounty_visibility'],
            'industry_tax_visibility' => $validated['industry_tax_visibility'],
            'pvp_visibility' => $validated['pvp_visibility'],
            'fleet_participation_visibility' => $validated['fleet_participation_visibility'],
            'mining_weight' => (float) $validated['mining_weight'],
            'pve_bounty_weight' => (float) $validated['pve_bounty_weight'],
            'industry_tax_weight' => (float) $validated['industry_tax_weight'],
            'pvp_weight' => (float) $validated['pvp_weight'],
            'fleet_participation_weight' => (float) $validated['fleet_participation_weight'],
            'fleet_participation_min_size' => (int) $validated['fleet_participation_min_size'],
            'minimum_home_presence' => (int) $validated['minimum_home_presence'],
        ]);

        return redirect()->route('member-engagement.settings')
            ->with('success', 'Settings saved successfully!');
    }

    public function addRegion(Request $request, string $list)
    {
        $this->authorize();

        $column = $this->regionColumn($list);

        $validated = $request->validate([
            'region_id' => 'required|integer|exists:regions,region_id',
        ]);

        $setting = MemberEngagementSetting::current();
        $regionId = (int) $validated['region_id'];

        // A region belongs to one list or the other, never both - adding it
        // to one takes it out of the other.
        $lists = [
            'home_regions' => $this->without($setting->home_regions, $regionId),
            'deployment_regions' => $this->without($setting->deployment_regions, $regionId),
        ];
        $lists[$column][] = $regionId;

        $setting->update($lists);

        return redirect()->route('member-engagement.settings')
            ->with('success', 'Region added.');
    }

    public function removeRegion(Request $request, string $list)
    {
        $this->authorize();

        $column = $this->regionColumn($list);

        $validated = $request->validate([
            'region_id' => 'required|integer',
        ]);

        $setting = MemberEngagementSetting::current();

        $setting->update([
            $column => $this->without($setting->{$column}, (int) $validated['region_id']),
        ]);

        return redirect()->route('member-engagement.settings')
            ->with('success', 'Region removed.');
    }

    private function regionColumn(string $list): string
    {
        return match ($list) {
            'home' => 'home_regions',
            'deployment' => 'deployment_regions',
            default => abort(404, 'Unknown region list'),
        };
    }

    /** @return int[] */
    private function without(?array $regions, int $regionId): array
    {
        return array_values(array_diff(array_map('intval', $regions ?? []), [$regionId]));
    }

    private function authorize(): void
    {
        if (!Auth::user()->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }
    }
}

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

        $regions = DB::table('regions')->orderBy('name')->get(['region_id', 'name']);

        return view('member-engagement::settings.index', [
            'setting' => $setting,
            'homeRegions' => $regions->whereIn('region_id', $homeRegionIds)->values(),
            'availableRegions' => $regions->whereNotIn('region_id', $homeRegionIds)->values(),
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

    public function addHomeRegion(Request $request)
    {
        $this->authorize();

        $validated = $request->validate([
            'region_id' => 'required|integer|exists:regions,region_id',
        ]);

        $setting = MemberEngagementSetting::current();
        $regions = array_map('intval', $setting->home_regions ?? []);
        $regions[] = (int) $validated['region_id'];

        $setting->update(['home_regions' => array_values(array_unique($regions))]);

        return redirect()->route('member-engagement.settings')
            ->with('success', 'Home region added.');
    }

    public function removeHomeRegion(Request $request)
    {
        $this->authorize();

        $validated = $request->validate([
            'region_id' => 'required|integer',
        ]);

        $setting = MemberEngagementSetting::current();
        $regions = array_map('intval', $setting->home_regions ?? []);

        $setting->update([
            'home_regions' => array_values(array_diff($regions, [(int) $validated['region_id']])),
        ]);

        return redirect()->route('member-engagement.settings')
            ->with('success', 'Home region removed.');
    }

    private function authorize(): void
    {
        if (!Auth::user()->can('member-engagement.view_all_activities')) {
            abort(403, 'Unauthorized');
        }
    }
}

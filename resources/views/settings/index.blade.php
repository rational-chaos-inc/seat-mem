@extends('web::layouts.grids.12')

@section('title', 'Member Engagement Module Settings')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Member Engagement Module Settings</h3>
                <div class="small text-muted">
                    Applies to every main character and their linked alts, regardless of corporation.
                </div>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('member-engagement.settings.store') }}" method="POST">
                    @csrf

                    <!-- Metrics Configuration -->
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th style="width: 300px;">Visibility</th>
                                <th style="width: 120px;">Weight (0-10)</th>
                                <th style="width: 100px;">Min Fleet Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Login Status -->
                            <tr>
                                <td>
                                    <strong>Login Status</strong>
                                    <div class="small text-muted">Daily login tracking</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="login_visibility" value="directors"
                                            {{ $setting->login_visibility === 'directors' ? 'checked' : '' }}
                                            id="login_directors">
                                        <label class="btn btn-outline-secondary" for="login_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="login_visibility" value="members"
                                            {{ $setting->login_visibility === 'members' ? 'checked' : '' }}
                                            id="login_members">
                                        <label class="btn btn-outline-secondary" for="login_members">Members</label>

                                        <input type="radio" class="btn-check" name="login_visibility" value="both"
                                            {{ $setting->login_visibility === 'both' ? 'checked' : '' }}
                                            id="login_both">
                                        <label class="btn btn-outline-secondary" for="login_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="mining_weight"
                                        value="{{ $setting->mining_weight }}"
                                        style="display: none;">
                                </td>
                                <td></td>
                            </tr>

                            <!-- Mining Activity -->
                            <tr>
                                <td>
                                    <strong>Mining Activity</strong>
                                    <div class="small text-muted">Ore mined and value generated</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="mining_visibility" value="directors"
                                            {{ $setting->mining_visibility === 'directors' ? 'checked' : '' }}
                                            id="mining_directors">
                                        <label class="btn btn-outline-secondary" for="mining_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="mining_visibility" value="members"
                                            {{ $setting->mining_visibility === 'members' ? 'checked' : '' }}
                                            id="mining_members">
                                        <label class="btn btn-outline-secondary" for="mining_members">Members</label>

                                        <input type="radio" class="btn-check" name="mining_visibility" value="both"
                                            {{ $setting->mining_visibility === 'both' ? 'checked' : '' }}
                                            id="mining_both">
                                        <label class="btn btn-outline-secondary" for="mining_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="mining_weight"
                                        value="{{ $setting->mining_weight }}">
                                </td>
                                <td></td>
                            </tr>

                            <!-- PvE Bounty & Tax -->
                            <tr>
                                <td>
                                    <strong>PvE Bounty &amp; Tax</strong>
                                    <div class="small text-muted">Bounty prizes and daily goal payouts</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="pve_bounty_visibility" value="directors"
                                            {{ $setting->pve_bounty_visibility === 'directors' ? 'checked' : '' }}
                                            id="pve_bounty_directors">
                                        <label class="btn btn-outline-secondary" for="pve_bounty_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="pve_bounty_visibility" value="members"
                                            {{ $setting->pve_bounty_visibility === 'members' ? 'checked' : '' }}
                                            id="pve_bounty_members">
                                        <label class="btn btn-outline-secondary" for="pve_bounty_members">Members</label>

                                        <input type="radio" class="btn-check" name="pve_bounty_visibility" value="both"
                                            {{ $setting->pve_bounty_visibility === 'both' ? 'checked' : '' }}
                                            id="pve_bounty_both">
                                        <label class="btn btn-outline-secondary" for="pve_bounty_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="pve_bounty_weight"
                                        value="{{ $setting->pve_bounty_weight }}">
                                </td>
                                <td></td>
                            </tr>

                            <!-- Industry Tax -->
                            <tr>
                                <td>
                                    <strong>Industry Tax</strong>
                                    <div class="small text-muted">Facility tax from industry jobs</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="industry_tax_visibility" value="directors"
                                            {{ $setting->industry_tax_visibility === 'directors' ? 'checked' : '' }}
                                            id="industry_tax_directors">
                                        <label class="btn btn-outline-secondary" for="industry_tax_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="industry_tax_visibility" value="members"
                                            {{ $setting->industry_tax_visibility === 'members' ? 'checked' : '' }}
                                            id="industry_tax_members">
                                        <label class="btn btn-outline-secondary" for="industry_tax_members">Members</label>

                                        <input type="radio" class="btn-check" name="industry_tax_visibility" value="both"
                                            {{ $setting->industry_tax_visibility === 'both' ? 'checked' : '' }}
                                            id="industry_tax_both">
                                        <label class="btn btn-outline-secondary" for="industry_tax_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="industry_tax_weight"
                                        value="{{ $setting->industry_tax_weight }}">
                                </td>
                                <td></td>
                            </tr>

                            <!-- PvP Activity -->
                            <tr>
                                <td>
                                    <strong>PvP Activity</strong>
                                    <div class="small text-muted">All kills and losses</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="pvp_visibility" value="directors"
                                            {{ $setting->pvp_visibility === 'directors' ? 'checked' : '' }}
                                            id="pvp_directors">
                                        <label class="btn btn-outline-secondary" for="pvp_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="pvp_visibility" value="members"
                                            {{ $setting->pvp_visibility === 'members' ? 'checked' : '' }}
                                            id="pvp_members">
                                        <label class="btn btn-outline-secondary" for="pvp_members">Members</label>

                                        <input type="radio" class="btn-check" name="pvp_visibility" value="both"
                                            {{ $setting->pvp_visibility === 'both' ? 'checked' : '' }}
                                            id="pvp_both">
                                        <label class="btn btn-outline-secondary" for="pvp_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="pvp_weight"
                                        value="{{ $setting->pvp_weight }}">
                                </td>
                                <td></td>
                            </tr>

                            <!-- Fleet Participation -->
                            <tr>
                                <td>
                                    <strong>Fleet Participation</strong>
                                    <div class="small text-muted">Kills with N+ fleet members</div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="fleet_participation_visibility" value="directors"
                                            {{ $setting->fleet_participation_visibility === 'directors' ? 'checked' : '' }}
                                            id="fleet_directors">
                                        <label class="btn btn-outline-secondary" for="fleet_directors">Directors</label>

                                        <input type="radio" class="btn-check" name="fleet_participation_visibility" value="members"
                                            {{ $setting->fleet_participation_visibility === 'members' ? 'checked' : '' }}
                                            id="fleet_members">
                                        <label class="btn btn-outline-secondary" for="fleet_members">Members</label>

                                        <input type="radio" class="btn-check" name="fleet_participation_visibility" value="both"
                                            {{ $setting->fleet_participation_visibility === 'both' ? 'checked' : '' }}
                                            id="fleet_both">
                                        <label class="btn btn-outline-secondary" for="fleet_both">Both</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                        name="fleet_participation_weight"
                                        value="{{ $setting->fleet_participation_weight }}">
                                </td>
                                <td>
                                    <input type="number" step="1" min="1" max="100" class="form-control form-control-sm"
                                        name="fleet_participation_min_size"
                                        value="{{ $setting->fleet_participation_min_size }}">
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Presence</th>
                                <th style="width: 160px;">Minimum (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Minimum Home + Deployed Presence</strong>
                                    <div class="small text-muted">
                                        Accounts with less than this share of their asset value in a
                                        home or deployment region are highlighted on Corporation View
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" max="100" class="form-control form-control-sm"
                                        name="minimum_home_presence"
                                        value="{{ $setting->minimum_home_presence }}">
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @include('member-engagement::settings.partials.region-list', [
            'list' => 'home',
            'title' => 'Home Regions',
            'blurb' => 'Assets held here count towards an account's presence.',
            'regions' => $homeRegions,
            'available' => $availableRegions,
            'emptyNote' => 'No home regions set yet - presence can't be calculated until at least one is added.',
        ])

        @include('member-engagement::settings.partials.region-list', [
            'list' => 'deployment',
            'title' => 'Deployment Regions',
            'blurb' => 'Where the corporation is currently deployed. Assets here count towards presence too.',
            'regions' => $deploymentRegions,
            'available' => $availableRegions,
            'emptyNote' => 'No deployment regions set - add one when the corporation deploys.',
        ])

    </div>
</div>
@endsection

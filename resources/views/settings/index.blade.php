@extends('web::layouts.grids.12')

@section('title', 'Member Engagement Module Settings')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Member Engagement Module Settings</h3>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @foreach($corporations as $corp)
                    @php $setting = $settings[$corp->corporation_id] ?? null; @endphp

                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">{{ $corp->name ?? 'Unknown Corporation' }}</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('member-engagement.settings.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="corporation_id" value="{{ $corp->corporation_id }}">

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
                                                        {{ $setting && $setting->login_visibility === 'directors' ? 'checked' : '' }}
                                                        id="login_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="login_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="login_visibility" value="members"
                                                        {{ $setting && $setting->login_visibility === 'members' ? 'checked' : '' }}
                                                        id="login_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="login_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="login_visibility" value="both"
                                                        {{ $setting && $setting->login_visibility === 'both' ? 'checked' : '' }}
                                                        id="login_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="login_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="mining_weight"
                                                    value="{{ $setting ? $setting->mining_weight : 1.0 }}"
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
                                                        {{ $setting && $setting->mining_visibility === 'directors' ? 'checked' : '' }}
                                                        id="mining_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="mining_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="mining_visibility" value="members"
                                                        {{ $setting && $setting->mining_visibility === 'members' ? 'checked' : '' }}
                                                        id="mining_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="mining_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="mining_visibility" value="both"
                                                        {{ $setting && $setting->mining_visibility === 'both' ? 'checked' : '' }}
                                                        id="mining_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="mining_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="mining_weight"
                                                    value="{{ $setting ? $setting->mining_weight : 1.0 }}">
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
                                                        {{ $setting && $setting->pve_bounty_visibility === 'directors' ? 'checked' : '' }}
                                                        id="pve_bounty_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pve_bounty_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="pve_bounty_visibility" value="members"
                                                        {{ $setting && $setting->pve_bounty_visibility === 'members' ? 'checked' : '' }}
                                                        id="pve_bounty_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pve_bounty_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="pve_bounty_visibility" value="both"
                                                        {{ $setting && $setting->pve_bounty_visibility === 'both' ? 'checked' : '' }}
                                                        id="pve_bounty_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pve_bounty_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="pve_bounty_weight"
                                                    value="{{ $setting ? $setting->pve_bounty_weight : 1.0 }}">
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
                                                        {{ $setting && $setting->industry_tax_visibility === 'directors' ? 'checked' : '' }}
                                                        id="industry_tax_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="industry_tax_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="industry_tax_visibility" value="members"
                                                        {{ $setting && $setting->industry_tax_visibility === 'members' ? 'checked' : '' }}
                                                        id="industry_tax_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="industry_tax_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="industry_tax_visibility" value="both"
                                                        {{ $setting && $setting->industry_tax_visibility === 'both' ? 'checked' : '' }}
                                                        id="industry_tax_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="industry_tax_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="industry_tax_weight"
                                                    value="{{ $setting ? $setting->industry_tax_weight : 1.0 }}">
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
                                                        {{ $setting && $setting->pvp_visibility === 'directors' ? 'checked' : '' }}
                                                        id="pvp_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pvp_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="pvp_visibility" value="members"
                                                        {{ $setting && $setting->pvp_visibility === 'members' ? 'checked' : '' }}
                                                        id="pvp_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pvp_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="pvp_visibility" value="both"
                                                        {{ $setting && $setting->pvp_visibility === 'both' ? 'checked' : '' }}
                                                        id="pvp_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="pvp_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="pvp_weight"
                                                    value="{{ $setting ? $setting->pvp_weight : 1.0 }}">
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
                                                        {{ $setting && $setting->fleet_participation_visibility === 'directors' ? 'checked' : '' }}
                                                        id="fleet_directors_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="fleet_directors_{{ $corp->corporation_id }}">Directors</label>

                                                    <input type="radio" class="btn-check" name="fleet_participation_visibility" value="members"
                                                        {{ $setting && $setting->fleet_participation_visibility === 'members' ? 'checked' : '' }}
                                                        id="fleet_members_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="fleet_members_{{ $corp->corporation_id }}">Members</label>

                                                    <input type="radio" class="btn-check" name="fleet_participation_visibility" value="both"
                                                        {{ $setting && $setting->fleet_participation_visibility === 'both' ? 'checked' : '' }}
                                                        id="fleet_both_{{ $corp->corporation_id }}">
                                                    <label class="btn btn-outline-secondary" for="fleet_both_{{ $corp->corporation_id }}">Both</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="10" class="form-control form-control-sm"
                                                    name="fleet_participation_weight"
                                                    value="{{ $setting ? $setting->fleet_participation_weight : 1.0 }}">
                                            </td>
                                            <td>
                                                <input type="number" step="1" min="1" max="100" class="form-control form-control-sm"
                                                    name="fleet_participation_min_size"
                                                    value="{{ $setting ? $setting->fleet_participation_min_size : 5 }}">
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
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@extends('web::layouts.grids.12')

@section('title', 'Corporation Activities')

@section('content')
<div class="row">
    <div class="col-md-12">

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Corporation Activities</h4>
                <div class="card-tools pull-right">
                    @if($isCurrentMonth)
                        <span class="badge badge-info">Live - current month in progress</span>
                    @else
                        <span class="badge badge-primary">Finalized</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <label>Month:</label>
                        <div class="btn-group flex-wrap" role="group">
                            @foreach($availableMonths as $availableMonth)
                                <a href="?month={{ $availableMonth->format('Y-m') }}"
                                   class="btn btn-sm @if($availableMonth->equalTo($month)) btn-primary @else btn-default @endif">
                                    {{ $availableMonth->format('M Y') }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <p class="text-muted mt-3 mb-0">
                    Scoped to registered SeAT members only - each entry is a main character with
                    any linked alts' activity consolidated under it. Characters whose owner hasn't
                    registered with SeAT aren't included here.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Tax Contributions</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['pve_bounty'],
                        'field' => 'pve_bounty_amount',
                        'heading' => 'PvE Bounty & Tax',
                        'subtitle' => 'Bounty prizes and daily goal payouts',
                        'valueLabel' => 'ISK',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['industry_tax'],
                        'field' => 'industry_tax_amount',
                        'heading' => 'Industry Tax',
                        'subtitle' => 'Facility tax from industry jobs',
                        'valueLabel' => 'ISK',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['mining_tax'],
                        'field' => 'mining_tax_amount',
                        'heading' => 'Mining Tax',
                        'subtitle' => 'Tax on mined ore, from Mining Manager',
                        'valueLabel' => 'ISK',
                    ])
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">PvP</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['kills'],
                        'field' => 'pvp_kills',
                        'heading' => 'Top PvP (Kills)',
                        'subtitle' => 'Killmails the member was an attacker on',
                        'valueLabel' => 'Kills',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['isk_destroyed'],
                        'field' => 'isk_destroyed',
                        'heading' => 'Top PvP (ISK Destroyed)',
                        'subtitle' => 'Full value of each kill, credited to every attacker',
                        'valueLabel' => 'ISK',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['fleet'],
                        'field' => 'fleet_participation',
                        'heading' => 'Top Fleet Participation',
                        'subtitle' => 'Configured minimum fleet size, 1/hour max',
                        'valueLabel' => 'Instances',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['losses'],
                        'field' => 'pvp_losses',
                        'heading' => 'Losses',
                        'subtitle' => 'Killmails the member was the victim on',
                        'valueLabel' => 'Losses',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['isk_lost'],
                        'field' => 'isk_lost',
                        'heading' => 'ISK Lost',
                        'subtitle' => 'Value of ships and fittings lost',
                        'valueLabel' => 'ISK',
                    ])
                </div>
                <p class="small text-muted mb-0">
                    ISK values are estimated from the victim's hull plus destroyed and dropped
                    items, priced against SeAT's market data - they won't match zKillboard exactly.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Heartbeat</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Days logged in this month, per registered SeAT account (main + linked
                    alts consolidated). Least active accounts are listed first.
                </p>

                @if(empty($heartbeat))
                    <div class="alert alert-info">No registered accounts to show</div>
                @else
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th class="text-right">Days Logged In ({{ $month->format('M Y') }})</th>
                                <th class="text-right">Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($heartbeat as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        {{ $row->display_name }}
                                        @if($row->member_count > 1)
                                            <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $row->days_logged_in }}</td>
                                    <td class="text-right">
                                        @if($row->logged_in_today)
                                            <span class="badge badge-success">Logged in today</span>
                                        @elseif($row->last_login_date)
                                            Last login on {{ $row->last_login_date->format('d/m/Y') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

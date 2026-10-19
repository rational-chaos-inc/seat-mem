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
                <div class="row mb-3">
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

                <p class="text-muted">
                    Scoped to registered SeAT members only - each entry is a main character with
                    any linked alts' activity consolidated under it. Characters whose owner hasn't
                    registered with SeAT aren't included here.
                </p>

                <div class="row">
                    <div class="col-md-3">
                        <h5>PvE Bounty &amp; Tax</h5>
                        <div class="small text-muted mb-2">Bounty prizes and daily goal payouts</div>
                        @if(empty($rankings['pve_bounty']))
                            <div class="alert alert-info">No data for this month</div>
                        @else
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th class="text-right">ISK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rankings['pve_bounty'] as $row)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $row->display_name }}
                                                @if($row->member_count > 1)
                                                    <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ number_format($row->pve_bounty_amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <h5>Industry Tax</h5>
                        <div class="small text-muted mb-2">Facility tax from industry jobs</div>
                        @if(empty($rankings['industry_tax']))
                            <div class="alert alert-info">No data for this month</div>
                        @else
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th class="text-right">ISK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rankings['industry_tax'] as $row)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $row->display_name }}
                                                @if($row->member_count > 1)
                                                    <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ number_format($row->industry_tax_amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <h5>Top PvP (Kills)</h5>
                        <div class="small text-muted mb-2">Total kills, all engagements</div>
                        @if(empty($rankings['kills']))
                            <div class="alert alert-info">No data for this month</div>
                        @else
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th class="text-right">Kills</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rankings['kills'] as $row)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $row->display_name }}
                                                @if($row->member_count > 1)
                                                    <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $row->pvp_kills }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="col-md-3">
                        <h5>Top Fleet Participation</h5>
                        <div class="small text-muted mb-2">Configured minimum fleet size, 1/hour max</div>
                        @if(empty($rankings['fleet']))
                            <div class="alert alert-info">No data for this month</div>
                        @else
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th class="text-right">Instances</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rankings['fleet'] as $row)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $row->display_name }}
                                                @if($row->member_count > 1)
                                                    <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $row->fleet_participation }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
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

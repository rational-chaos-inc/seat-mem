@extends('web::layouts.grids.12')

@section('title', 'League Tables')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">League Tables</h4>
            </div>
            <div class="card-body">
                <!-- Time Window & Type Selectors -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Time Window:</label>
                        <div class="btn-group" role="group">
                            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'] as $window => $label)
                                <a href="{{ route('member-engagement.league-tables', ['window' => $window, 'type' => $activityType]) }}"
                                   class="btn btn-sm {{ $timeWindow === $window ? 'btn-primary' : 'btn-outline-primary' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label>Activity Type:</label>
                        <div class="btn-group" role="group">
                            @foreach($activityTypes as $type)
                                <a href="{{ route('member-engagement.league-tables', ['window' => $timeWindow, 'type' => $type]) }}"
                                   class="btn btn-sm {{ $activityType === $type ? 'btn-primary' : 'btn-outline-primary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- League Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Total Value</th>
                                <th>Activities</th>
                                <th>Average</th>
                                <th>Characters</th>
                                <th>Per Character</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leagueTable as $rank => $member)
                                @php
                                    $memberModel = config('auth.providers.users.model');
                                    $memberUser = $memberModel::find($member['user_id']);
                                @endphp
                                @if($memberUser)
                                    <tr>
                                        <td>
                                            <strong>{{ $rank + 1 }}</strong>
                                            @if($rank === 0)
                                                <span class="badge badge-gold">🥇</span>
                                            @elseif($rank === 1)
                                                <span class="badge badge-silver">🥈</span>
                                            @elseif($rank === 2)
                                                <span class="badge badge-bronze">🥉</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $memberUser->name ?? $memberUser->email }}</strong></td>
                                        <td>{{ number_format($member['total_value'], 2) }} ISK</td>
                                        <td>{{ $member['count'] }}</td>
                                        <td>{{ number_format($member['average_value'], 2) }}</td>
                                        <td>{{ $member['character_count'] }}</td>
                                        <td>{{ number_format($member['value_per_character'], 2) }}</td>
                                        <td>
                                            <a href="{{ route('member-engagement.member.detail', ['userId' => $member['user_id'], 'window' => $timeWindow]) }}"
                                               class="btn btn-sm btn-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        No data available
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .badge-gold {
        background-color: #FFD700;
        color: #333;
    }
    .badge-silver {
        background-color: #C0C0C0;
        color: #333;
    }
    .badge-bronze {
        background-color: #CD7F32;
        color: #fff;
    }
</style>
@endsection

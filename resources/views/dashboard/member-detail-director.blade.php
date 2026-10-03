@extends('web::layouts.grids.12')

@section('title', ($member->name ?? $member->email) . ' - Member Activity Details')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ $member->name ?? $member->email }} - Member Activity Details</h4>
            </div>
            <div class="card-body">
                <!-- Time Window Selector -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Time Window:</label>
                        <div class="btn-group" role="group">
                            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'] as $window => $label)
                                <a href="{{ route('member-engagement.member.detail', ['userId' => $member->id, 'window' => $window]) }}"
                                   class="btn btn-sm {{ $timeWindow === $window ? 'btn-primary' : 'btn-outline-primary' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('member-engagement.director.index') }}" class="btn btn-secondary float-right">
                            ← Back to Director Dashboard
                        </a>
                    </div>
                </div>

                <!-- Character List -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h5>Linked Characters</h5>
                        <div class="list-group">
                            @foreach($memberCharacters as $character)
                                <div class="list-group-item">
                                    <h6 class="mb-1">{{ $character->character_name }}</h6>
                                    <p class="mb-0 text-muted small">
                                        Character ID: {{ $character->character_id }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Total Value</h6>
                                <h3 class="card-text">{{ number_format($aggregation->total, 2) }} ISK</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Total Activities</h6>
                                <h3 class="card-text">{{ $aggregation->count }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Average per Activity</h6>
                                <h3 class="card-text">{{ number_format($aggregation->average, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breakdown by Type -->
                @if(!empty($aggregation->byType))
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Breakdown by Activity Type</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Activity Type</th>
                                        <th>Count</th>
                                        <th>Total Value</th>
                                        <th>Average</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($aggregation->byType as $type => $stats)
                                        <tr>
                                            <td><strong>{{ ucfirst(str_replace('_', ' ', $type)) }}</strong></td>
                                            <td>{{ $stats['count'] }}</td>
                                            <td>{{ number_format($stats['total'], 2) }}</td>
                                            <td>{{ number_format($stats['average'], 2) }}</td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: {{ $stats['percentage'] }}%"
                                                         aria-valuenow="{{ $stats['percentage'] }}"
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        {{ $stats['percentage'] }}%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Breakdown by Character -->
                @if(!empty($aggregation->byCharacter))
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>Breakdown by Character</h5>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Character</th>
                                        <th>Count</th>
                                        <th>Total Value</th>
                                        <th>Average</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($memberCharacters as $character)
                                        @if(isset($aggregation->byCharacter[$character->character_id]))
                                            <tr>
                                                <td><strong>{{ $character->character_name }}</strong></td>
                                                <td>{{ $aggregation->byCharacter[$character->character_id]['count'] }}</td>
                                                <td>{{ number_format($aggregation->byCharacter[$character->character_id]['total'], 2) }}</td>
                                                <td>{{ number_format($aggregation->byCharacter[$character->character_id]['average'], 2) }}</td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar"
                                                             style="width: {{ $aggregation->byCharacter[$character->character_id]['percentage'] }}%"
                                                             aria-valuenow="{{ $aggregation->byCharacter[$character->character_id]['percentage'] }}"
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            {{ $aggregation->byCharacter[$character->character_id]['percentage'] }}%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

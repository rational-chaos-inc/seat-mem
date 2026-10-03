@extends('web::layouts.grids.12')

@section('title', $character->character_name . ' - Activity Details')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ $character->character_name }} - Activity Details</h4>
            </div>
            <div class="card-body">
                <!-- Time Window Selector -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Time Window:</label>
                        <div class="btn-group" role="group">
                            @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'] as $window => $label)
                                <a href="{{ route('member-engagement.character.detail', ['characterId' => $character->character_id, 'window' => $window]) }}"
                                   class="btn btn-sm {{ $timeWindow === $window ? 'btn-primary' : 'btn-outline-primary' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-6">
                        <a href="{{ route('member-engagement.dashboard') }}" class="btn btn-secondary float-right">
                            ← Back to Dashboard
                        </a>
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
                                <h6 class="card-title text-muted">Activities</h6>
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
                            <h5>Breakdown by Type</h5>
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
            </div>
        </div>
    </div>
</div>
@endsection

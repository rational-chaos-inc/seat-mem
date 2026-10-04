@extends('web::layouts.grids.12')

@section('title', 'My Activities')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">My Activities</h4>
                <div class="card-tools pull-right">
                    <span class="badge badge-primary">{{ count($activities) }} records</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label>Time Period:</label>
                        <div class="btn-group" role="group">
                            <a href="?window=day" class="btn btn-sm @if($timeWindow === 'day') btn-primary @else btn-default @endif">Day</a>
                            <a href="?window=week" class="btn btn-sm @if($timeWindow === 'week') btn-primary @else btn-default @endif">Week</a>
                            <a href="?window=month" class="btn btn-sm @if($timeWindow === 'month') btn-primary @else btn-default @endif">Month</a>
                            <a href="?window=quarter" class="btn btn-sm @if($timeWindow === 'quarter') btn-primary @else btn-default @endif">Quarter</a>
                            <a href="?window=year" class="btn btn-sm @if($timeWindow === 'year') btn-primary @else btn-default @endif">Year</a>
                        </div>
                    </div>
                </div>

                @if(count($activities) == 0)
                    <div class="alert alert-info">No activities recorded yet</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Character</th>
                                    <th>Type</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activities as $activity)
                                    <tr>
                                        <td>{{ $activity->activity_timestamp->format('Y-m-d H:i') }}</td>
                                        <td>{{ $activity->display_name }}</td>
                                        <td><span class="label label-primary">{{ $activity->activity_type }}</span></td>
                                        <td>
                                            @if($activity->metadata)
                                                @php $meta = $activity->metadata; @endphp
                                                @if(isset($meta['quantity']))
                                                    {{ $meta['quantity'] }} units
                                                @elseif(isset($meta['amount']))
                                                    ISK {{ number_format($meta['amount']) }}
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

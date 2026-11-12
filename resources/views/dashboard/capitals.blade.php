@extends('web::layouts.grids.12')

@section('title', 'Capital Tracking')

@section('content')
<div class="row">
    <div class="col-md-12">

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Capital Tracking</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">
                    Capital hulls held by registered SeAT members
                    @if($primaryCorporationName)
                        with at least one character in <strong>{{ $primaryCorporationName }}</strong>
                    @endif
                    - counted across every linked character on the account. Click a row to expand it.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">By Hull Type</h4>
            </div>
            <div class="card-body">
                @if(empty($types))
                    <div class="alert alert-info mb-0">No capital hulls found.</div>
                @else
                    <table class="table table-sm table-striped table-hover w-auto">
                        <thead>
                            <tr>
                                <th>Hull</th>
                                <th>Class</th>
                                <th class="text-right">Count</th>
                                <th class="text-right">Held by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($types as $type)
                                <tr class="me-expand" style="cursor: pointer;" data-target="type-{{ $type['type_id'] }}">
                                    <td>
                                        <img src="https://images.evetech.net/types/{{ $type['type_id'] }}/icon?size=32"
                                             width="24" height="24" class="mr-1 align-text-bottom" alt="" loading="lazy">
                                        {{ $type['type_name'] }}
                                    </td>
                                    <td>{{ $type['group_name'] }}</td>
                                    <td class="text-right">{{ $type['total'] }}</td>
                                    <td class="text-right">{{ count($type['holders']) }}</td>
                                </tr>
                                <tr id="type-{{ $type['type_id'] }}" hidden>
                                    <td colspan="4" class="bg-light">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Member</th>
                                                    <th class="text-right">Held</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($type['holders'] as $holder)
                                                    <tr>
                                                        <td>{{ $holder['name'] }}</td>
                                                        <td class="text-right">{{ $holder['count'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">By Member</h4>
            </div>
            <div class="card-body">
                @if(empty($accounts))
                    <div class="alert alert-info mb-0">No members to show.</div>
                @else
                    <div style="overflow-x: auto;">
                        <table class="table table-sm table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    @foreach($groups as $groupName)
                                        <th class="text-right">{{ $groupName }}</th>
                                    @endforeach
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accounts as $account)
                                    <tr class="me-expand" style="cursor: pointer;"
                                        data-target="member-{{ $account['main_character_id'] }}">
                                        <td>
                                            {{ $account['name'] }}
                                            @if($account['member_count'] > 1)
                                                <span class="badge badge-secondary" title="Consolidated characters">{{ $account['member_count'] }}</span>
                                            @endif
                                        </td>
                                        @foreach($groups as $groupId => $groupName)
                                            <td class="text-right">
                                                @if($account['counts'][$groupId] > 0)
                                                    {{ $account['counts'][$groupId] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-right"><strong>{{ $account['total'] }}</strong></td>
                                    </tr>
                                    <tr id="member-{{ $account['main_character_id'] }}" hidden>
                                        <td colspan="{{ count($groups) + 2 }}" class="bg-light">
                                            @if(empty($account['ships']))
                                                <span class="text-muted">No capital hulls.</span>
                                            @else
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Hull</th>
                                                            <th>Class</th>
                                                            <th>Character</th>
                                                            <th>System</th>
                                                            <th>Region</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($account['ships'] as $ship)
                                                            <tr>
                                                                <td>
                                                                    <img src="https://images.evetech.net/types/{{ $ship['type_id'] }}/icon?size=32"
                                                                         width="24" height="24" class="mr-1 align-text-bottom" alt="" loading="lazy">
                                                                    {{ $ship['type_name'] }}
                                                                    @if($ship['quantity'] > 1)
                                                                        &times;{{ $ship['quantity'] }}
                                                                    @endif
                                                                </td>
                                                                <td>{{ $ship['group_name'] }}</td>
                                                                <td>{{ $ship['character_name'] }}</td>
                                                                <td>{{ $ship['system'] }}</td>
                                                                <td>{{ $ship['region'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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

@push('javascript')
<script type="text/javascript">
    $(function () {
        // Both tables carry their detail rows inline, hidden - the data is
        // small enough that a round trip per row would just add latency.
        $('tr.me-expand').on('click', function () {
            var detail = $('#' + $(this).data('target'));
            detail.prop('hidden', !detail.prop('hidden'));
            $(this).toggleClass('table-active');
        });
    });
</script>
@endpush

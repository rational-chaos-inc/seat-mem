@extends('web::layouts.grids.12')

@section('title', 'Capital Tracking')

@section('content')
@push('head')
<style>
    /* Chart roles as tokens, so the dark steps swap in one place rather
       than being sprinkled through the markup. Both scopes are declared:
       the media query covers the OS setting, the data-theme scope covers
       a theme toggle, and the toggle has to win either way. */
    .me-viz {
        --me-surface:        #fcfcfb;
        --me-ink:            #0b0b0b;
        --me-ink-muted:      #52514e;
        --me-grid:           rgba(11, 11, 11, 0.07);
        --me-band-support:   #2a78d6;
        --me-band-combat:    #eb6834;
        --me-band-capital:   #1baf7a;
    }
    @media (prefers-color-scheme: dark) {
        :root:where(:not([data-theme="light"])) .me-viz {
            --me-surface:      #1a1a19;
            --me-ink:          #ffffff;
            --me-ink-muted:    #c3c2b7;
            --me-grid:         rgba(255, 255, 255, 0.10);
            --me-band-support: #3987e5;
            --me-band-combat:  #d95926;
            --me-band-capital: #199e70;
        }
    }
    :root[data-theme="dark"] .me-viz {
        --me-surface:      #1a1a19;
        --me-ink:          #ffffff;
        --me-ink-muted:    #c3c2b7;
        --me-grid:         rgba(255, 255, 255, 0.10);
        --me-band-support: #3987e5;
        --me-band-combat:  #d95926;
        --me-band-capital: #199e70;
    }

    /* Plot height plus room for the x-axis band, so the card never grows
       its own little scrollbar. */
    .me-fleet-chart { position: relative; height: 320px; }

    .me-class-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.35rem 0;
    }
    .me-class-row + .me-class-row { border-top: 1px solid var(--me-grid); }
    .me-class-art {
        width: 64px; height: 64px; flex: 0 0 64px;
        border-radius: 4px; object-fit: cover;
        background: var(--me-grid);
    }
    .me-class-body { flex: 1 1 auto; min-width: 0; }
    .me-class-name {
        font-size: 0.875rem; color: var(--me-ink);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .me-class-track {
        height: 10px; border-radius: 4px;
        background: var(--me-grid); overflow: hidden;
    }
    /* 4px rounded data-end, anchored to the baseline at the left. */
    .me-class-bar {
        height: 100%; border-radius: 0 4px 4px 0; min-width: 2px;
    }
    .me-class-bar[data-band="support"] { background: var(--me-band-support); }
    .me-class-bar[data-band="combat"]  { background: var(--me-band-combat); }
    .me-class-bar[data-band="capital"] { background: var(--me-band-capital); }
    .me-class-figures {
        flex: 0 0 5.5rem; text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .me-class-count { font-size: 1.05rem; color: var(--me-ink); }
    .me-class-change { font-size: 0.75rem; color: var(--me-ink-muted); }
    .me-legend {
        display: flex; flex-wrap: wrap; gap: 0.25rem 1rem;
        font-size: 0.8125rem; color: var(--me-ink-muted);
    }
    .me-legend span { display: inline-flex; align-items: center; gap: 0.35rem; }
    .me-legend i {
        width: 10px; height: 10px; border-radius: 2px; display: inline-block;
    }
</style>
@endpush

<div class="row">
    <div class="col-md-12">

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Capital Tracking</h4>
                <div class="card-tools pull-right">
                    @if($generatedAt)
                        <span class="small text-muted">
                            As of {{ $generatedAt->format('d/m/Y H:i') }} UTC
                            ({{ $generatedAt->diffForHumans() }})
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">
                    Capital hulls held by registered SeAT members
                    @if($primaryCorporationName)
                        with at least one character in <strong>{{ $primaryCorporationName }}</strong>
                    @endif
                    - counted across every linked character on the account. Click a row to expand it.
                </p>
                @if(!empty($missingGroups))
                    <div class="alert alert-warning mt-2 mb-2">
                        <strong>{{ implode(', ', $missingGroups) }}</strong>
                        {{ count($missingGroups) === 1 ? 'is' : 'are' }}
                        not counted below - neither this install's SDE nor this
                        plugin can resolve
                        {{ count($missingGroups) === 1 ? 'that class' : 'those classes' }}.
                        SeAT pins the SDE to a single build, so a class CCP has
                        added recently needs its hull type ids adding to the
                        plugin. Please report it.
                    </div>
                @endif
                <p class="small text-muted mb-0">
                    Rebuilt every three hours rather than on each page load - walking every
                    character's assets is too slow to do while you wait. Run
                    <code>php artisan member-engagement:refresh-capitals</code> for an
                    immediate rebuild.
                </p>
            </div>
        </div>

        <div class="card me-viz">
            <div class="card-header">
                <h4 class="card-title">Capital Fleet Over Time</h4>
            </div>
            <div class="card-body">
                @if($fleetHistory['points'] < 2)
                    <div class="alert alert-info mb-0">
                        Not enough history yet. SeAT only holds assets as they are right
                        now, so this builds up from here - a reading every three hours
                        for the month in progress, and one kept for each month end.
                    </div>
                @else
                    <div class="me-legend mb-2">
                        @foreach($bands as $key => $band)
                            <span>
                                <i style="background: var(--me-band-{{ $key }});"></i>
                                {{ $band['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <div class="me-fleet-chart">
                        <canvas id="me-fleet-history"></canvas>
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        Hulls held by tracked accounts, stacked to the fleet total.
                        @if($fleetHistory['current_month_from'] !== null)
                            Month-end figures, then one point per day for the month in
                            progress.
                        @else
                            One point per month end.
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
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
            </div>

            <div class="col-xl-6">
                <div class="card me-viz">
                    <div class="card-header">
                        <h4 class="card-title">By Class</h4>
                    </div>
                    <div class="card-body">
                        @php($mostHeld = collect($classSummary)->max('hulls') ?: 1)
                        @foreach($classSummary as $class)
                            <div class="me-class-row">
                                @if($class['type_id'])
                                    <img class="me-class-art"
                                         src="https://images.evetech.net/types/{{ $class['type_id'] }}/render?size=128"
                                         alt="" loading="lazy">
                                @else
                                    <span class="me-class-art"></span>
                                @endif
                                <div class="me-class-body">
                                    <div class="me-class-name">{{ $class['group_name'] }}</div>
                                    <div class="me-class-track">
                                        <div class="me-class-bar" data-band="{{ $class['band'] }}"
                                             style="width: {{ $class['hulls'] > 0 ? max(2, round(($class['hulls'] / $mostHeld) * 100)) : 0 }}%;"></div>
                                    </div>
                                </div>
                                <div class="me-class-figures">
                                    <div class="me-class-count">{{ $class['hulls'] }}</div>
                                    <div class="me-class-change">
                                        @if($class['change'] === null)
                                            <span title="No figure recorded for last month yet">no history</span>
                                        @elseif($class['change'] > 0)
                                            &#9650; +{{ $class['change'] }}
                                        @elseif($class['change'] < 0)
                                            &#9660; &minus;{{ abs($class['change']) }}
                                        @else
                                            &mdash;
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <p class="small text-muted mb-0 mt-2">
                            Change is against the figure held at the end of last month.
                        </p>
                    </div>
                </div>
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
                                                                <td>
                                                                    {{ $ship['system'] }}
                                                                    @if($ship['in_space'])
                                                                        <span class="badge badge-warning ml-1"
                                                                              title="Left in space, not docked">in space</span>
                                                                    @endif
                                                                </td>
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
                            <tfoot>
                                <tr class="font-weight-bold bg-light border-top">
                                    <td>Total ({{ count($accounts) }} members)</td>
                                    @foreach($groups as $groupId => $groupName)
                                        <td class="text-right">
                                            {{ collect($accounts)->sum(fn($account) => $account['counts'][$groupId]) ?: '-' }}
                                        </td>
                                    @endforeach
                                    <td class="text-right">{{ collect($accounts)->sum('total') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">By Region</h4>
            </div>
            <div class="card-body">
                @if(empty($regions))
                    <div class="alert alert-info mb-0">No capital hulls found.</div>
                @else
                    <div style="overflow-x: auto;">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Region</th>
                                    @foreach($groups as $groupName)
                                        <th class="text-right">{{ $groupName }}</th>
                                    @endforeach
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            @foreach($regions as $region)
                                <tbody>
                                    <tr class="me-expand" style="cursor: pointer;"
                                        data-target="region-{{ $loop->index }}">
                                        <td>
                                            {{ $region['region'] }}
                                            @if($region['in_space'] > 0)
                                                <span class="badge badge-warning ml-1"
                                                      title="Left in space, not docked">{{ $region['in_space'] }} in space</span>
                                            @endif
                                        </td>
                                        @foreach($groups as $groupId => $groupName)
                                            <td class="text-right">
                                                @if($region['counts'][$groupId] > 0)
                                                    {{ $region['counts'][$groupId] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-right"><strong>{{ $region['total'] }}</strong></td>
                                    </tr>
                                </tbody>
                                <tbody id="region-{{ $loop->index }}" hidden>
                                    @foreach($region['holders'] as $holder)
                                        <tr class="bg-light">
                                            <td class="pl-4">{{ $holder['name'] }}</td>
                                            @foreach($groups as $groupId => $groupName)
                                                <td class="text-right">
                                                    @if($holder['counts'][$groupId] > 0)
                                                        {{ $holder['counts'][$groupId] }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="text-right">{{ $holder['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                            <tfoot>
                                <tr class="font-weight-bold bg-light border-top">
                                    <td>Total ({{ count($regions) }} regions)</td>
                                    @foreach($groups as $groupId => $groupName)
                                        <td class="text-right">
                                            {{ collect($regions)->sum(fn($region) => $region['counts'][$groupId]) ?: '-' }}
                                        </td>
                                    @endforeach
                                    <td class="text-right">{{ collect($regions)->sum('total') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('javascript')
@if($fleetHistory['points'] >= 2)
<script type="text/javascript">
    $(function () {
        var root = document.querySelector('.me-viz');
        var canvas = document.getElementById('me-fleet-history');

        if (!root || !canvas || typeof Chart === 'undefined') {
            return;
        }

        // Chart.js cannot read CSS custom properties, so the tokens are
        // resolved once at load - which also means the chart picks up
        // whichever theme is active.
        var token = function (name, fallback) {
            var value = getComputedStyle(root).getPropertyValue(name).trim();
            return value || fallback;
        };

        var wash = function (hex) {
            var m = /^#?([0-9a-f]{6})$/i.exec(hex);
            if (!m) {
                return hex;
            }
            var n = parseInt(m[1], 16);
            // A wash, not a block: the 2px line carries the edge.
            return 'rgba(' + [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(',') + ',0.18)';
        };

        var ink = token('--me-ink-muted', '#52514e');
        var grid = token('--me-grid', 'rgba(0,0,0,0.07)');
        var history = @json($fleetHistory);
        var bands = @json($bands);

        var datasets = Object.keys(bands).map(function (key, index) {
            var hue = token('--me-band-' + key, '#2a78d6');

            return {
                label: bands[key].label,
                data: history.series[key] || [],
                backgroundColor: wash(hue),
                borderColor: hue,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHoverBackgroundColor: hue,
                pointHoverBorderColor: token('--me-surface', '#fcfcfb'),
                pointHoverBorderWidth: 2,
                // The first band fills to the axis, the rest to the band
                // below, which is what makes the stack read as one total.
                fill: index === 0 ? 'origin' : '-1',
            };
        });

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: history.labels,
                datasets: datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // Identity comes from the HTML legend above the plot, which
                // survives printing and does not steal plot height.
                legend: { display: false },
                elements: { line: { tension: 0.4 } },
                hover: { mode: 'index', intersect: false },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        afterBody: function (items) {
                            var total = items.reduce(function (sum, item) {
                                return sum + Number(item.yLabel || 0);
                            }, 0);
                            return 'Fleet total: ' + total;
                        },
                    },
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: {
                            fontColor: ink,
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        },
                    }],
                    yAxes: [{
                        stacked: true,
                        gridLines: {
                            color: grid,
                            zeroLineColor: grid,
                            drawBorder: false,
                        },
                        ticks: {
                            beginAtZero: true,
                            precision: 0,
                            fontColor: ink,
                        },
                        scaleLabel: {
                            display: true,
                            labelString: 'Hulls',
                            fontColor: ink,
                        },
                    }],
                },
            },
        });
    });
</script>
@endif
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

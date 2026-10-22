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
                        'heading' => 'Top PvP (Losses)',
                        'subtitle' => 'Killmails the member was the victim on',
                        'valueLabel' => 'Losses',
                    ])
                    @include('member-engagement::dashboard.partials.leaderboard', [
                        'rows' => $rankings['isk_lost'],
                        'field' => 'isk_lost',
                        'heading' => 'Top PvP (ISK Lost)',
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
                <h4 class="card-title">Net Worth</h4>
                <div class="card-tools pull-right">
                    @if($netWorthTakenAt)
                        <span class="small text-muted">Snapshot taken {{ $netWorthTakenAt->format('d/m/Y') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(empty($netWorth))
                    <div class="alert alert-info">
                        No snapshot recorded yet - this is captured once a day at 04:23 UTC, or run
                        <code>php artisan member-engagement:snapshot-net-worth</code> to take one now.
                    </div>
                @else
                    <table class="table table-sm table-striped table-hover me-sortable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th class="text-right">Net Worth</th>
                                <th class="text-right">Assets</th>
                                <th class="text-right">ISK and PLEX</th>
                                <th class="text-right">Home Presence</th>
                                <th class="text-right">Change vs Last Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($netWorth as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        {{ $row->display_name }}
                                        @if($row->member_count > 1)
                                            <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($row->net_worth) }}</td>
                                    <td class="text-right">{{ number_format($row->asset_value) }}</td>
                                    <td class="text-right"
                                        title="Wallet {{ number_format($row->wallet_balance) }} ISK + PLEX {{ number_format($row->plex_value) }} ISK">
                                        {{ number_format($row->currency_value) }}
                                    </td>
                                    <td class="text-right">
                                        @if($row->home_presence === null)
                                            <span class="text-muted">-</span>
                                        @elseif($row->below_threshold)
                                            <span class="badge badge-danger">{{ number_format($row->home_presence, 1) }}%</span>
                                        @else
                                            {{ number_format($row->home_presence, 1) }}%
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if($row->change_percent === null)
                                            <span class="text-muted">-</span>
                                        @else
                                            <span @class(['text-danger' => $row->change_percent < 0, 'text-success' => $row->change_percent > 0])>
                                                {{ $row->change_percent > 0 ? '+' : '' }}{{ number_format($row->change_percent, 1) }}%
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        Assets are priced against SeAT's market data, excluding blueprint copies.
                        ISK and PLEX covers personal wallets and vaulted PLEX at market value -
                        not corporation wallets. Hover for the split.
                    </p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Home Presence</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Share of each account's asset value sitting in
                    @if(empty($homeRegionNames))
                        <strong>no regions yet</strong> - set your home regions on the Settings page.
                    @else
                        <strong>{{ implode(', ', $homeRegionNames) }}</strong>.
                    @endif
                    Anything below {{ $minimumHomePresence }}% is highlighted. Least present accounts
                    first - select a member to see where their assets actually sit.
                </p>

                @php
                    $placedAccounts = collect($netWorth)
                        ->filter(fn($r) => $r->home_presence !== null)
                        ->sortBy('home_presence')
                        ->values();
                @endphp

                @if($placedAccounts->isEmpty() || empty($homeRegionNames))
                    <div class="alert alert-info">Nothing to compare yet</div>
                @else
                    <div class="row">
                        <div class="col-lg-7">
                            <table class="table table-sm table-striped table-hover w-auto me-sortable" id="home-presence-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th class="text-right">Home Presence</th>
                                        <th class="text-right">Value at Home</th>
                                        <th class="text-right">Value Elsewhere</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($placedAccounts as $row)
                                        <tr class="me-region-row @if($row->below_threshold) table-danger @endif"
                                            style="cursor: pointer;"
                                            data-member="{{ $row->display_name }}"
                                            data-unresolved="{{ (int) $row->unresolved_value }}"
                                            data-home="{{ number_format($row->home_presence, 1) }}"
                                            data-character="{{ $row->character_id }}"
                                            data-slices="{{ json_encode($row->chart_slices) }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $row->display_name }}
                                                @if($row->member_count > 1)
                                                    <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if($row->below_threshold)
                                                    <span class="badge badge-danger">{{ number_format($row->home_presence, 1) }}%</span>
                                                @else
                                                    {{ number_format($row->home_presence, 1) }}%
                                                @endif
                                            </td>
                                            <td class="text-right">{{ number_format($row->value_at_home) }}</td>
                                            <td class="text-right">{{ number_format($row->value_elsewhere) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="col-lg-5">
                            <h6 class="text-muted" id="me-region-title">Assets by region</h6>
                            <div style="position: relative; height: 230px;">
                                <canvas id="me-region-chart"></canvas>
                            </div>
                            <table class="table table-sm mt-2 mb-0" id="me-region-legend"></table>
                            <p class="small text-muted mb-0" id="me-region-note"></p>
                        </div>
                    </div>
                @endif
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
                    <table class="table table-sm table-striped table-hover me-sortable">
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

<div class="modal fade" id="me-unknown-modal" tabindex="-1" role="dialog" aria-labelledby="me-unknown-modal-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="me-unknown-modal-title">Assets in unknown locations</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    SeAT couldn't resolve these containers to a station, structure or system -
                    usually because it has no docking access to the structure holding them.
                    They're excluded from the home presence calculation.
                </p>
                <div id="me-unknown-body"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('javascript')
<script type="text/javascript">
    $(function () {
        var canvas = document.getElementById('me-region-chart');
        if (!canvas) {
            return;
        }

        // Chart.js 2.9 is already loaded by SeAT's layout.
        var chart = null;
        var selected = null;
        var unknownUrl = @json(route('member-engagement.director.unresolved-assets', ['characterId' => 0]));

        function escapeHtml(text) {
            return $('<div>').text(text).html();
        }

        function hasUnknown(slices) {
            return slices.some(function (slice) { return slice.region_id === 'unknown'; });
        }

        function openUnknown() {
            if (!selected || !hasUnknown(selected.slices)) {
                return;
            }

            var body = $('#me-unknown-body');
            body.html('<p class="text-muted mb-0">Loading...</p>');
            $('#me-unknown-modal-title').text('Unknown locations - ' + selected.member);
            $('#me-unknown-modal').modal('show');

            $.getJSON(unknownUrl.replace(/0$/, selected.characterId))
                .done(function (data) {
                    if (!data.locations || !data.locations.length) {
                        body.html('<p class="text-muted mb-0">Nothing to show.</p>');
                        return;
                    }
                    body.html(data.locations.map(function (location) {
                        return '<h6 class="mb-1">Container ' + escapeHtml(location.location_id) + '</h6>'
                            + '<div class="small text-muted mb-1">'
                            + Math.round(location.total_value).toLocaleString() + ' ISK</div>'
                            + renderItems(location.items);
                    }).join(''));
                })
                .fail(function () {
                    body.html('<p class="text-danger mb-0">Could not load the asset list.</p>');
                });
        }

        function renderItems(items) {
            if (!items || !items.length) {
                return '';
            }

            return '<ul class="list-unstyled ml-3 mb-2">' + items.map(function (item) {
                return '<li>'
                    + escapeHtml(item.name)
                    + (item.quantity > 1 ? ' &times;' + item.quantity.toLocaleString() : '')
                    + (item.value > 0
                        ? ' <span class="text-muted small">(' + Math.round(item.value).toLocaleString() + ' ISK)</span>'
                        : '')
                    + renderItems(item.children)
                    + '</li>';
            }).join('') + '</ul>';
        }

        function render(member, slices, unresolved, home, characterId) {
            selected = { member: member, slices: slices, characterId: characterId };
            $('#me-region-title').text('Assets by region - ' + member);

            // These slices are of everything held. Home presence ignores
            // the unknown slice, so when there is one the two figures
            // differ - say why rather than leaving it looking wrong.
            $('#me-region-note').text(
                unresolved > 0
                    ? 'Home presence (' + home + '%) is measured against located assets only, '
                        + 'so it ignores the ' + Math.round(unresolved).toLocaleString()
                        + ' ISK SeAT could not place.'
                    : ''
            );

            $('#me-region-legend').html(slices.map(function (slice) {
                var isUnknown = slice.region_id === 'unknown';
                return '<tr' + (isUnknown ? ' class="me-unknown-link" style="cursor:pointer;"' : '') + '>'
                    + '<td style="width:1.5rem;"><span style="display:inline-block;width:.75rem;height:.75rem;'
                    + 'border-radius:2px;background:' + slice.colour + ';"></span></td>'
                    + '<td>' + escapeHtml(slice.name)
                    + (isUnknown ? ' <span class="badge badge-light">view</span>' : '') + '</td>'
                    + '<td class="text-right">' + slice.percent.toFixed(1) + '%</td>'
                    + '</tr>';
            }).join(''));

            if (chart) {
                chart.destroy();
            }

            chart = new Chart(canvas.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: slices.map(function (slice) { return slice.name; }),
                    datasets: [{
                        data: slices.map(function (slice) { return slice.value; }),
                        backgroundColor: slices.map(function (slice) { return slice.colour; }),
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: function (event, elements) {
                        if (elements.length && slices[elements[0]._index].region_id === 'unknown') {
                            openUnknown();
                        }
                    },
                    // Identity comes from the table legend beside the chart,
                    // so the built-in one would just repeat it.
                    legend: { display: false },
                    tooltips: {
                        callbacks: {
                            label: function (item, data) {
                                var values = data.datasets[0].data;
                                var value = values[item.index];
                                var total = values.reduce(function (a, b) { return a + b; }, 0);
                                var percent = total ? (value / total * 100).toFixed(1) : '0.0';

                                return data.labels[item.index] + ': ' + percent
                                    + '% (' + Math.round(value).toLocaleString() + ' ISK)';
                            }
                        }
                    }
                }
            });
        }

        var rows = $('#home-presence-table tr.me-region-row');

        rows.on('click', function () {
            var row = $(this);
            rows.removeClass('table-active');
            row.addClass('table-active');
            render(
                row.data('member'), row.data('slices'),
                row.data('unresolved'), row.data('home'), row.data('character')
            );
        });

        $('#me-region-legend').on('click', 'tr.me-unknown-link', openUnknown);

        // Open on the least-present account - the one worth looking at.
        rows.first().trigger('click');
    });
</script>
<script type="text/javascript">
    // Click any column header on the Corporation View tables to sort by it.
    // Rows are moved rather than re-rendered, so the Home Presence row
    // handlers and their data attributes survive a sort.
    $(function () {
        function cellValue(row, index) {
            var text = $(row).children('td').eq(index).text().trim();

            // Numbers first: strips thousands separators, %, and ISK.
            var numeric = text.replace(/[,%\s]/g, '').replace(/ISK/i, '');
            if (numeric !== '' && !isNaN(numeric)) {
                return { type: 'number', value: parseFloat(numeric) };
            }

            // dd/mm/yyyy, so "Last Login" sorts chronologically.
            var date = text.match(/(\d{2})\/(\d{2})\/(\d{4})/);
            if (date) {
                return { type: 'number', value: Date.UTC(+date[3], date[2] - 1, +date[1]) };
            }

            // "Logged in today" has to outrank every real date.
            if (/logged in today/i.test(text)) {
                return { type: 'number', value: Date.now() };
            }
            if (/never/i.test(text)) {
                return { type: 'number', value: -1 };
            }

            return { type: 'text', value: text.toLowerCase() };
        }

        $('table.me-sortable').each(function () {
            var table = $(this);
            var headers = table.find('thead th');
            var rankColumn = headers.first().text().trim() === '#';

            headers.each(function (index) {
                // The rank column just numbers whatever order we're in.
                if (rankColumn && index === 0) {
                    return;
                }

                var header = $(this);
                header.css('cursor', 'pointer').attr('title', 'Sort by ' + header.text().trim());

                header.on('click', function () {
                    var ascending = header.data('sortAsc') !== true;
                    headers.data('sortAsc', null).find('.me-sort-caret').remove();
                    header.data('sortAsc', ascending);
                    header.append(' <span class="me-sort-caret small text-muted">'
                        + (ascending ? '&uarr;' : '&darr;') + '</span>');

                    var body = table.children('tbody');
                    body.children('tr').get()
                        .sort(function (a, b) {
                            var left = cellValue(a, index);
                            var right = cellValue(b, index);
                            var result = left.type === 'number' && right.type === 'number'
                                ? left.value - right.value
                                : String(left.value).localeCompare(String(right.value));

                            return ascending ? result : -result;
                        })
                        .forEach(function (row) {
                            body.append(row);
                        });

                    if (rankColumn) {
                        body.children('tr').each(function (position) {
                            $(this).children('td').eq(0).text(position + 1);
                        });
                    }
                });
            });
        });
    });
</script>
@endpush

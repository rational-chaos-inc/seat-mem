{{--
    One ranked leaderboard column.

    $rows       array of consolidated account rows (see RankingService)
    $field      property on each row to display and rank by
    $heading    column heading
    $subtitle   small muted line under the heading
    $valueLabel header for the value column
    $width      optional bootstrap column class
--}}
<div class="{{ $width ?? 'col-lg-4 col-md-6' }}">
    <h5>{{ $heading }}</h5>
    <div class="small text-muted mb-2">{{ $subtitle }}</div>
    @if(empty($rows))
        <div class="alert alert-info">No data for this month</div>
    @else
        <table class="table table-sm table-striped table-hover me-sortable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th class="text-right">{{ $valueLabel }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $row->display_name }}
                            @if($row->member_count > 1)
                                <span class="badge badge-secondary" title="Consolidated characters">{{ $row->member_count }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($row->{$field}) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

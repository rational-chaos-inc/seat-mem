{{--
    One managed region list.

    $list       'home' or 'deployment' - which list this manages
    $title      card heading
    $blurb      small line under the heading
    $regions    collection of {region_id, name} currently in the list
    $available  collection of regions not yet in either list
    $emptyNote  shown when the list has nothing in it
--}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $title }}</h3>
        <div class="small text-muted">{{ $blurb }}</div>
    </div>
    <div class="card-body">
        @if($regions->isEmpty())
            <div class="alert alert-info">{{ $emptyNote }}</div>
        @else
            <table class="table table-sm table-striped" style="max-width: 420px;">
                <tbody>
                    @foreach($regions as $region)
                        <tr>
                            <td>{{ $region->name }}</td>
                            <td class="text-right" style="width: 60px;">
                                <form action="{{ route('member-engagement.settings.regions.remove', ['list' => $list]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="region_id" value="{{ $region->region_id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="Remove {{ $region->name }}">&times;</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <form action="{{ route('member-engagement.settings.regions.add', ['list' => $list]) }}" method="POST" class="form-inline">
            @csrf
            <select name="region_id" class="form-control form-control-sm mr-2" required>
                <option value="">Add a region...</option>
                @foreach($available as $region)
                    <option value="{{ $region->region_id }}">{{ $region->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Add</button>
        </form>
    </div>
</div>

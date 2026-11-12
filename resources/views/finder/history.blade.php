@extends('layouts.app')
@section('title', __('Search history'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="page-title">{{ __('Search history') }}</h1>
        <form class="ms-auto" method="GET">
            <div class="input-group input-group-sm">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search history') }}">
                <button class="btn btn-success"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>{{ __('Search') }}</th><th class="d-none d-md-table-cell">{{ __('Target') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Businesses added') }}</th><th class="d-none d-sm-table-cell">{{ __('When') }}</th><th class="text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($searches as $s)
                    @php $q = array_filter($s->only(['business_name', 'match_type', 'keywords', 'district', 'city', 'area', 'pincode', 'quantity']) + ['category' => $s->category]); @endphp
                    <tr>
                        <td><div class="fw-semibold">{{ $s->query }}</div>
                            <div class="small text-muted">@if ($s->category){{ \App\Support\Categories::label($s->category) }}@endif @if ($s->district) · {{ $s->district }}@endif</div></td>
                        <td class="d-none d-md-table-cell">{{ $s->quantity }}</td>
                        <td class="d-none d-md-table-cell">{{ $s->business_leads_count }}</td>
                        <td class="d-none d-sm-table-cell small text-muted">{{ $s->created_at->format('d M Y, h:i A') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ app(\App\Services\BusinessFinder\SearchLinkBuilder::class)->build($q)['google_maps_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Google Maps"><i class="bi bi-geo-alt"></i></a>
                            <a href="{{ route('finder.results', $q) }}" class="btn btn-sm btn-light" title="{{ __('Open again') }}"><i class="bi bi-arrow-repeat"></i></a>
                            <a href="{{ route('businesses.create', ['search_id' => $s->id]) }}" class="btn btn-sm btn-light" title="{{ __('Add business') }}"><i class="bi bi-building-add"></i></a>
                            <form method="POST" action="{{ route('finder.history.destroy', $s->id) }}" class="d-inline" data-confirm="{{ __('Remove this search from history?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-clock-history"></i>{{ __('No searches yet.') }} <a href="{{ route('finder.search') }}">{{ __('Start searching') }}</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $searches->links() }}</div>
@endsection

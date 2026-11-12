@extends('layouts.app')
@section('title', __('Business Finder'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h1 class="page-title"><i class="bi bi-compass text-orange me-1"></i>{{ __('Business Finder') }}</h1>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('finder.search') }}" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>{{ __('Search businesses') }}</a>
            <a href="{{ route('businesses.create') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-building-add me-1"></i>{{ __('Add business') }}</a>
        </div>
    </div>
    <p class="text-muted mb-4">{{ __('Find potential website customers across Tamil Nadu with free Google Maps and Google Search links, verify them, and save them to your CRM.') }}</p>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('Researched'), 'value' => $stats['total_researched'], 'icon' => 'buildings', 'tone' => 'navy', 'href' => route('businesses.index')])</div>
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('Saved to CRM'), 'value' => $stats['saved_to_crm'], 'icon' => 'person-check', 'tone' => 'teal', 'href' => route('businesses.index', ['saved' => 1])])</div>
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('No website'), 'value' => $stats['no_website'], 'icon' => 'globe2', 'tone' => 'orange', 'href' => route('businesses.index', ['scope' => 'no_website'])])</div>
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('Website available'), 'value' => $stats['website_available'], 'icon' => 'window', 'tone' => 'teal', 'href' => route('businesses.index', ['website_status' => 'Website available'])])</div>
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('Verified contacts'), 'value' => $stats['contact_verified'], 'icon' => 'patch-check', 'tone' => 'teal', 'href' => route('businesses.index', ['scope' => 'verified_contacts'])])</div>
        <div class="col-6 col-md-4 col-xl-2">@include('partials.stat', ['label' => __('Pending verification'), 'value' => $stats['pending_verification'], 'icon' => 'hourglass-split', 'tone' => 'red', 'href' => route('businesses.index', ['contact' => 'pending'])])</div>
        <div class="col-4 col-xl-4">@include('partials.stat', ['label' => __('New leads'), 'value' => $stats['new_leads'], 'icon' => 'stars', 'tone' => 'navy', 'href' => route('businesses.index', ['lead_status' => 'New'])])</div>
        <div class="col-4 col-xl-4">@include('partials.stat', ['label' => __('Interested'), 'value' => $stats['interested'], 'icon' => 'hand-thumbs-up', 'tone' => 'orange', 'href' => route('businesses.index', ['lead_status' => 'Interested'])])</div>
        <div class="col-4 col-xl-4">@include('partials.stat', ['label' => __('Converted'), 'value' => $stats['converted'], 'icon' => 'trophy', 'tone' => 'teal', 'href' => route('businesses.index', ['lead_status' => 'Won'])])</div>
    </div>

    <div class="alert bg-teal-subtle border-0 d-flex gap-2"><i class="bi bi-info-circle text-teal"></i><div>
        @if ($stats['total_researched'])
            {{ __(':researched researched, :saved saved to CRM. :nowebsite with no website, :verified contacts verified, :pending pending verification. :interested interested, :converted converted.', [
                'researched' => $stats['total_researched'], 'saved' => $stats['saved_to_crm'], 'nowebsite' => $stats['no_website'],
                'verified' => $stats['contact_verified'], 'pending' => $stats['pending_verification'],
                'interested' => $stats['interested'], 'converted' => $stats['converted'],
            ]) }}
        @else
            {{ __('No businesses researched yet. Start with a search in Business Finder.') }}
        @endif
    </div></div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-lightbulb text-orange me-1"></i>{{ __('Suggested searches') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($suggestions as $s)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-grow-1"><span class="fw-semibold">{{ \App\Support\Categories::label($s['category']) }}</span> <span class="text-muted">{{ __('in') }} {{ $s['city'] }}</span></div>
                            <a href="{{ $s['links']['google_maps_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Google Maps"><i class="bi bi-geo-alt"></i></a>
                            <a href="{{ route('finder.results', ['category' => $s['category'], 'city' => $s['city']]) }}" class="btn btn-sm btn-light">{{ __('All links') }}</a>
                        </div>
                    @empty
                        <div class="empty-state">{{ __('You have searched every category recently.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-pie-chart text-teal me-1"></i>{{ __('By category') }}</div>
                <div class="card-body">
                    @php $max = max(1, $byCategory->max() ?? 1); @endphp
                    @forelse ($byCategory as $cat => $n)
                        <a href="{{ route('businesses.index', ['category' => $cat]) }}" class="d-block text-decoration-none text-body mb-2">
                            <div class="d-flex justify-content-between small mb-1"><span>{{ \App\Support\Categories::label($cat) }}</span><span class="fw-semibold">{{ $n }}</span></div>
                            <div class="progress" style="height:7px"><div class="progress-bar bg-success" style="width: {{ $n * 100 / $max }}%"></div></div>
                        </a>
                    @empty
                        <div class="empty-state py-3">{{ __('No businesses yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header d-flex"><span><i class="bi bi-hourglass-split text-danger me-1"></i>{{ __('Waiting for verification') }}</span>
                    <a href="{{ route('businesses.index', ['contact' => 'pending']) }}" class="ms-auto small">{{ __('View all') }}</a></div>
                <div class="list-group list-group-flush">
                    @forelse ($needsVerification as $b)
                        <a href="{{ route('businesses.show', $b) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">{{ $b->business_name }}</div><div class="small text-muted">{{ $b->city }}</div></div>
                            <x-badge :value="$b->website_status" /><x-badge :value="$b->contact_status" />
                        </a>
                    @empty
                        <div class="empty-state py-3"><i class="bi bi-patch-check"></i>{{ __('All caught up.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex"><span><i class="bi bi-clock-history me-1"></i>{{ __('Recently added') }}</span>
                    <a href="{{ route('businesses.index') }}" class="ms-auto small">{{ __('View all') }}</a></div>
                <div class="list-group list-group-flush">
                    @forelse ($recent as $b)
                        <a href="{{ route('businesses.show', $b) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">{{ $b->business_name }}</div><div class="small text-muted">{{ \App\Support\Categories::label($b->category) }} · {{ $b->city }}</div></div>
                            @if ($b->lead)<x-badge :value="$b->lead->status" />@else<span class="badge bg-light text-muted">{{ __('Not saved') }}</span>@endif
                        </a>
                    @empty
                        <div class="empty-state py-3"><i class="bi bi-building-add"></i>{{ __('No businesses yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

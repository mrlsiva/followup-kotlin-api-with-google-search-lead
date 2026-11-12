@extends('layouts.app')
@section('title', __('Search businesses'))
@push('scripts-before')
    <script>window.CRM_DISTRICTS = @json(config('business_finder.districts'));</script>
@endpush
@section('content')
    @php $p = $params ?: request()->all(); @endphp
    <h1 class="page-title mb-1">{{ __('Search businesses') }}</h1>
    <p class="text-muted">{{ __('Free manual research: we build Google Maps and Google Search links, you check the businesses and add the good ones to your CRM. Nothing is scraped and no paid API is used.') }}</p>

    <form method="GET" action="{{ route('finder.results') }}" class="card mb-4" novalidate>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="category">{{ __('Business category') }}</label>
                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach (\App\Support\Categories::searchable() as $c)<option value="{{ $c }}" @selected(($p['category'] ?? '') === $c)>{{ \App\Support\Categories::label($c) }}</option>@endforeach
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="business_name">{{ __('Business name') }} <span class="text-muted small">({{ __('optional') }})</span></label>
                    <div class="input-group">
                        <input type="text" name="business_name" id="business_name" value="{{ $p['business_name'] ?? '' }}" class="form-control" maxlength="150">
                        <select name="match_type" class="form-select" style="max-width: 9rem" aria-label="{{ __('Match') }}">
                            <option value="partial" @selected(($p['match_type'] ?? 'partial') === 'partial')>{{ __('Partial') }}</option>
                            <option value="exact" @selected(($p['match_type'] ?? '') === 'exact')>{{ __('Exact') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="keywords">{{ __('Keywords') }}</label>
                    <input type="text" name="keywords" id="keywords" value="{{ $p['keywords'] ?? '' }}" class="form-control @error('keywords') is-invalid @enderror" placeholder="{{ __('e.g. dental, wholesale') }}" maxlength="150">
                    @error('keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label" for="district">{{ __('District') }}</label>
                    <select name="district" id="district" class="form-select @error('district') is-invalid @enderror" data-district-for="city-list">
                        <option value="">{{ __('All Tamil Nadu') }}</option>
                        @foreach (array_keys(config('business_finder.districts')) as $d)<option value="{{ $d }}" @selected(($p['district'] ?? '') === $d)>{{ $d }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="city">{{ __('City / town') }}</label>
                    <input type="text" name="city" id="city" list="city-list" value="{{ $p['city'] ?? '' }}" class="form-control" maxlength="80">
                    <datalist id="city-list"></datalist>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="area">{{ __('Area') }}</label>
                    <input type="text" name="area" id="area" value="{{ $p['area'] ?? '' }}" class="form-control" maxlength="100">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="pincode">{{ __('PIN code') }}</label>
                    <input type="text" name="pincode" id="pincode" value="{{ $p['pincode'] ?? '' }}" class="form-control @error('pincode') is-invalid @enderror" inputmode="numeric" maxlength="6" pattern="6[0-9]{5}">
                    @error('pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-4 col-md-2">
                    <label class="form-label" for="quantity">{{ __('Target') }}</label>
                    <select name="quantity" id="quantity" class="form-select">
                        @foreach (config('business_finder.quantities') as $q)<option value="{{ $q }}" @selected((int) ($p['quantity'] ?? 20) === $q)>{{ $q }}</option>@endforeach
                    </select>
                </div>
                <div class="col-8 col-md-4">
                    <label class="form-label" for="website_filter">{{ __('Website availability') }}</label>
                    <select name="website_filter" id="website_filter" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach (\App\Enums\WebsiteStatus::values() as $w)<option value="{{ $w }}" @selected(($p['website_filter'] ?? '') === $w)>{{ __($w) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contact_filter">{{ __('Contact availability') }}</label>
                    <select name="contact_filter" id="contact_filter" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['phone_available' => 'Phone available', 'phone_missing' => 'Phone missing', 'pending' => 'Contact verification pending', 'verified' => 'Contact verified'] as $v => $l)
                            <option value="{{ $v }}" @selected(($p['contact_filter'] ?? '') === $v)>{{ __($l) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>{{ __('Search') }}</button>
                </div>
            </div>
        </div>
    </form>

    @if ($links)
        <div class="card mb-4 border-success">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <span><i class="bi bi-link-45deg text-teal me-1"></i>{{ __('Search links for') }} <span class="text-teal">“{{ $links['query'] }}”</span></span>
                <a href="{{ route('businesses.create', ['search_id' => $search->id]) }}" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-building-add me-1"></i>{{ __('Add Business to CRM') }}</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    @foreach ($links['links'] as $l)
                        @php $icon = ['maps' => 'geo-alt-fill', 'contact' => 'telephone', 'website' => 'globe2', 'directory' => 'card-list'][$l['type']] ?? 'link'; @endphp
                        <div class="col-sm-6 col-xl-3">
                            <a href="{{ $l['url'] }}" target="_blank" rel="noopener" class="link-tile">
                                <i class="bi bi-{{ $icon }}"></i>
                                <div><div class="fw-semibold">{{ __($l['label']) }}</div><div class="small text-muted">{{ $l['type'] === 'maps' ? 'Google Maps' : 'Google Search' }} <i class="bi bi-box-arrow-up-right"></i></div></div>
                            </a>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 small">
                    <span class="text-muted">{{ __('Result pages') }} ({{ __('target') }} {{ $links['target_count'] }}):</span>
                    @foreach ($links['result_pages'] as $page)
                        <a href="{{ $page['url'] }}" target="_blank" rel="noopener" class="btn btn-light btn-sm">{{ $page['page'] }}</a>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm ms-auto" data-copy="{{ $links['google_maps_url'] }}" title="{{ __('Copy Maps link') }}"><i class="bi bi-clipboard"></i> {{ __('Copy Maps link') }}</button>
                </div>
                <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>{{ __('Open the links, check each business (phone, website, address), then click "Add Business to CRM". Google may show fewer results than the target; it is only a research goal.') }}</div>
            </div>
        </div>

        @if (! empty($already) && $already->isNotEmpty())
            <div class="card">
                <div class="card-header">{{ __('Already in your list for this search') }}</div>
                <div class="list-group list-group-flush">
                    @foreach ($already as $b)
                        <a href="{{ route('businesses.show', $b) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                            <span class="flex-grow-1">{{ $b->business_name }} <span class="text-muted small">{{ $b->phone }}</span></span>
                            <x-badge :value="$b->website_status" />
                            @if ($b->lead)<x-badge :value="$b->lead->status" />@endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endsection

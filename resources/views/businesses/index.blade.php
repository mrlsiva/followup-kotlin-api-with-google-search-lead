@extends('layouts.app')
@section('title', __('Saved businesses'))
@section('content')
    @php $q = request()->except('page'); @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="page-title">{{ __('Saved businesses') }} <span class="text-muted fs-6 fw-normal">({{ $businesses->total() }})</span></h1>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>{{ __('Export') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">{{ __('Filtered list') }}</h6></li>
                    @foreach (['csv' => 'CSV', 'excel' => 'Excel', 'pdf' => 'PDF'] as $f => $l)
                        <li><a class="dropdown-item" href="{{ route('businesses.export', ['format' => $f] + $q) }}">{{ $l }}</a></li>
                    @endforeach
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">{{ __('Selected rows') }} (<span data-selected-count="ids">0</span>)</h6></li>
                    @foreach (['csv' => 'CSV', 'excel' => 'Excel', 'pdf' => 'PDF'] as $f => $l)
                        <li><a class="dropdown-item" data-needs-selection="ids" data-export-selected="ids" href="{{ route('businesses.export', ['format' => $f]) }}">{{ $l }}</a></li>
                    @endforeach
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">{{ __('Presets') }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('businesses.export', ['format' => 'excel', 'scope' => 'verified_contacts']) }}">{{ __('Verified contacts') }} (Excel)</a></li>
                    <li><a class="dropdown-item" href="{{ route('businesses.export', ['format' => 'excel', 'scope' => 'no_website']) }}">{{ __('No website found') }} (Excel)</a></li>
                    <li><a class="dropdown-item" href="{{ route('businesses.export', ['format' => 'pdf', 'scope' => 'followups']) }}">{{ __('Follow-up list') }} (PDF)</a></li>
                </ul>
            </div>
            <a href="{{ route('businesses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add business') }}</a>
        </div>
    </div>

    <form class="card card-body mb-3" method="GET">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Business, contact, phone, city…') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="category" class="form-select" data-autosubmit>
                    <option value="">{{ __('All categories') }}</option>
                    @foreach (\App\Support\Categories::all()->filter->isAssignable()->pluck('name') as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ \App\Support\Categories::label($c) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="website_status" class="form-select" data-autosubmit>
                    <option value="">{{ __('Any website') }}</option>
                    @foreach (\App\Enums\WebsiteStatus::values() as $w)<option value="{{ $w }}" @selected(request('website_status') === $w)>{{ __($w) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="contact" class="form-select" data-autosubmit>
                    <option value="">{{ __('Any contact') }}</option>
                    @foreach (['phone_available' => 'Phone available', 'phone_missing' => 'Phone missing', 'pending' => 'Contact verification pending', 'verified' => 'Contact verified'] as $v => $l)
                        <option value="{{ $v }}" @selected(request('contact') === $v)>{{ __($l) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="saved" class="form-select" data-autosubmit>
                    <option value="">{{ __('Saved or not') }}</option>
                    <option value="1" @selected(request('saved') === '1')>{{ __('Saved to CRM') }}</option>
                    <option value="0" @selected(request('saved') === '0')>{{ __('Not saved') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="text" name="city" value="{{ request('city') }}" class="form-control" placeholder="{{ __('City') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="lead_status" class="form-select" data-autosubmit>
                    <option value="">{{ __('Any lead status') }}</option>
                    @foreach (\App\Enums\LeadStatus::values() as $v)<option value="{{ $v }}" @selected(request('lead_status') === $v)>{{ __($v) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="scope" class="form-select" data-autosubmit>
                    <option value="all">{{ __('All records') }}</option>
                    <option value="verified_contacts" @selected(request('scope') === 'verified_contacts')>{{ __('Verified contacts') }}</option>
                    <option value="no_website" @selected(request('scope') === 'no_website')>{{ __('No website found') }}</option>
                    <option value="followups" @selected(request('scope') === 'followups')>{{ __('With follow-up') }}</option>
                </select>
            </div>
            @if ($users->isNotEmpty())
                <div class="col-6 col-md-2">
                    <select name="owner" class="form-select" data-autosubmit>
                        <option value="">{{ auth()->user()->isAdmin() ? __('All salespeople') : __('My team') }}</option>
                        <option value="me" @selected(request('owner') === 'me')>{{ __('Only mine') }}</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('owner') == $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
                <button class="btn btn-success"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                @if ($q)<a href="{{ route('businesses.index') }}" class="btn btn-light">{{ __('Clear') }}</a>@endif
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input" data-check-all="ids" aria-label="{{ __('Select all') }}"></th>
                    <th>{{ __('Business') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Phone') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Location') }}</th>
                    <th>{{ __('Website') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Contact') }}</th>
                    <th class="d-none d-sm-table-cell">{{ __('CRM') }}</th>
                    @if ($users->isNotEmpty())<th class="d-none d-xl-table-cell">{{ __('Owner') }}</th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse ($businesses as $b)
                    <tr>
                        <td><input type="checkbox" class="form-check-input" data-row-check="ids" value="{{ $b->id }}" aria-label="{{ __('Select') }}"></td>
                        <td>
                            <a href="{{ route('businesses.show', $b) }}" class="fw-semibold text-decoration-none">{{ $b->business_name }}</a>
                            <div class="small text-muted">{{ \App\Support\Categories::label($b->category) }}@if ($b->contact_person) · {{ $b->contact_person }}@endif</div>
                        </td>
                        <td class="d-none d-md-table-cell">@if ($b->phone)<a href="tel:{{ $b->phone }}" class="text-decoration-none">{{ $b->phone }}</a>@else<span class="text-muted">—</span>@endif</td>
                        <td class="d-none d-lg-table-cell small">{{ collect([$b->area, $b->city])->filter()->implode(', ') ?: '—' }}</td>
                        <td><x-badge :value="$b->website_status" /></td>
                        <td class="d-none d-md-table-cell"><x-badge :value="$b->contact_status" /></td>
                        <td class="d-none d-sm-table-cell">
                            @if ($b->lead)<a href="{{ route('leads.show', $b->lead) }}" class="text-decoration-none"><x-badge :value="$b->lead->status" /></a>
                            @else<span class="badge bg-light text-muted">{{ __('Not saved') }}</span>@endif
                        </td>
                        @if ($users->isNotEmpty())<td class="d-none d-xl-table-cell small">{{ $b->user?->name }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state"><i class="bi bi-buildings"></i>{{ __('No businesses match the filters.') }} <a href="{{ route('finder.search') }}">{{ __('Start searching') }}</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $businesses->links() }}</div>
    <p class="small text-muted mt-2"><i class="bi bi-shield-check me-1"></i>{{ __('Exports leave out "Do not contact" businesses.') }}</p>
@endsection

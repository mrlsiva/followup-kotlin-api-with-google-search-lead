@extends('layouts.app')
@section('title', $b->business_name)
@section('content')
    @php $lead = $b->lead; @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('businesses.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="page-title">{{ $b->business_name }}</h1>
            <div class="text-muted small">{{ \App\Support\Categories::label($b->category) }} · {{ collect([$b->area, $b->city, $b->district])->filter()->implode(', ') ?: __('Tamil Nadu') }}</div>
        </div>
        <div class="d-flex flex-wrap gap-1 ms-2">
            <x-badge :value="$b->website_status" data-bind="website_status" /><x-badge :value="$b->contact_status" />
        </div>
        @if ($isOwner)
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('businesses.edit', $b) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('businesses.destroy', $b) }}" data-confirm="{{ __('Remove this business? The CRM lead (if any) is kept.') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        @endif
    </div>

    @unless ($isOwner)
        <div class="alert alert-info py-2"><i class="bi bi-eye me-1"></i>{{ __('Researched by :name (read-only).', ['name' => $b->user?->name]) }}</div>
    @endunless
    @if ($b->do_not_contact)
        <div class="alert alert-dark py-2"><i class="bi bi-slash-circle me-1"></i>{{ __('Do not contact: no calls can be scheduled and this business is left out of exports.') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Business details') }}</div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <dt>{{ __('Phone') }}</dt><dd>@if ($b->phone)<a href="tel:{{ $b->phone }}">{{ $b->phone }}</a>@else — @endif</dd>
                        <dt>{{ __('Contact person') }}</dt><dd>{{ $b->contact_person ?: '—' }}</dd>
                        <dt>{{ __('Email') }}</dt><dd>@if ($b->email)<a href="mailto:{{ $b->email }}">{{ $b->email }}</a>@else — @endif</dd>
                        <dt>{{ __('Address') }}</dt><dd>{{ $b->address ?: '—' }}@if ($b->pincode) – {{ $b->pincode }}@endif</dd>
                        <dt>{{ __('Contact source') }}</dt><dd>{{ $b->contact_source ?: '—' }}@if ($b->source_url)<br><a href="{{ $b->source_url }}" target="_blank" rel="noopener nofollow" class="small">{{ \Illuminate\Support\Str::limit($b->source_url, 45) }} <i class="bi bi-box-arrow-up-right"></i></a>@endif</dd>
                        <dt>{{ __('Verified on') }}</dt><dd>{{ $b->contact_verified_at?->format('d M Y') ?? '—' }}</dd>
                        <dt>{{ __('Researched by') }}</dt><dd>{{ $b->user?->name }} · {{ $b->created_at->format('d M Y') }}</dd>
                        @if ($b->search)<dt>{{ __('Found via') }}</dt><dd class="small">{{ $b->search->query }}</dd>@endif
                    </dl>
                    @if ($b->notes)
                        <hr><div class="small text-muted mb-1">{{ __('Notes') }}</div><div style="white-space: pre-line">{{ $b->notes }}</div>
                    @endif
                    @php $mapsQuery = trim($b->business_name.' '.($b->area ?? '').' '.($b->city ?? '')); @endphp
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($mapsQuery) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-geo-alt me-1"></i>Google Maps</a>
                        <a href="https://www.google.com/search?{{ http_build_query(['q' => $mapsQuery.' contact number']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-search me-1"></i>Google</a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-globe2 me-1"></i>{{ __('Website verification') }}</div>
                <div class="card-body">
                    <dl class="dl-grid mb-3">
                        <dt>{{ __('Website URL') }}</dt>
                        <dd>@if ($b->website_url)<a href="{{ $b->website_url }}" target="_blank" rel="noopener nofollow" data-bind="website_url">{{ $b->website_url }}</a>@else<span data-bind="website_url">—</span>@endif</dd>
                        <dt>{{ __('Status') }}</dt><dd><span data-bind="website_status">{{ __($b->website_status->value) }}</span></dd>
                        <dt>{{ __('Last checked') }}</dt><dd data-bind="website_checked_at">{{ $b->website_checked_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                        <dt>{{ __('HTTP status') }}</dt><dd data-bind="website_http_status">{{ $b->website_http_status ?? '—' }}</dd>
                    </dl>
                    @if ($b->website_check_message)<div class="small text-muted mb-3">{{ $b->website_check_message }}</div>@endif
                    @if ($isOwner)
                        <div class="input-group">
                            <input type="text" id="check_url" class="form-control" value="{{ $b->website_url }}" placeholder="example.com">
                            <button type="button" class="btn btn-success" data-website-check data-url="{{ route('businesses.verify-website', $b) }}" data-input="#check_url" data-target="#web-result">
                                <i class="bi bi-activity me-1"></i>{{ __('Check website') }}
                            </button>
                        </div>
                        <div class="form-text">{{ __('A failed check never means the website does not exist; the status only changes automatically when the site clearly responds.') }}</div>
                        <div id="web-result" class="mt-2"></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            {{-- CRM lead --}}
            <div class="card mb-4 {{ $lead ? '' : 'border-warning' }}">
                <div class="card-header d-flex align-items-center"><span><i class="bi bi-person-badge me-1"></i>{{ __('CRM lead') }}</span>
                    @if ($lead)<a href="{{ route('leads.show', $lead) }}" class="ms-auto small">{{ __('Open lead') }}</a>@endif</div>
                <div class="card-body">
                    @if ($lead)
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="fw-semibold">{{ $lead->name }}</span><x-badge :value="$lead->status" /><x-badge :value="$lead->priority" />
                        </div>
                        <div class="mb-3">
                            <span class="text-muted">{{ __('Next follow-up') }}:</span>
                            <span class="fw-semibold {{ $lead->next_followup_at?->isPast() ? 'text-overdue' : '' }}">{{ $lead->next_followup_at?->format('D, d M Y · h:i A') ?? __('Not scheduled') }}</span>
                        </div>
                        @if ($isOwner && ! $lead->do_not_contact)
                            <form method="POST" action="{{ route('leads.update', $lead) }}" class="row g-2 align-items-end" novalidate>
                                @csrf @method('PUT')
                                <div class="col-12 small text-muted">{{ __('Schedule a call (uses the CRM follow-up rules)') }}</div>
                                <div class="col-6 col-md-5"><input type="date" name="next_followup_date" min="{{ today()->toDateString() }}" value="{{ $lead->next_followup_at?->format('Y-m-d') }}" class="form-control" required aria-label="{{ __('Date') }}"></div>
                                <div class="col-6 col-md-3"><input type="time" name="next_followup_time" value="{{ $lead->next_followup_at?->format('H:i') ?? '10:00' }}" class="form-control" aria-label="{{ __('Time') }}"></div>
                                <div class="col-12 col-md-4"><button class="btn btn-primary w-100"><i class="bi bi-calendar-plus me-1"></i>{{ __('Schedule') }}</button></div>
                            </form>
                        @endif
                        @if ($lead->followups->isNotEmpty())
                            <hr>
                            <ul class="timeline">
                                @foreach ($lead->followups->take(6) as $f)
                                    <li class="{{ $f->isPending() ? ($f->isOverdue() ? 'overdue' : 'pending') : '' }}">
                                        <span class="fw-semibold">{{ ($f->completed_at ?? $f->scheduled_at)->format('d M Y, h:i A') }}</span>
                                        @if ($f->isPending())<x-badge value="pending" />@else<x-badge :value="$f->call_status" />@endif
                                        @if ($f->note)<div class="small text-body-secondary">{{ $f->note }}</div>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif ($isOwner)
                        <p class="text-muted">{{ __('This business is not in your CRM yet.') }}</p>
                        <form method="POST" action="{{ route('businesses.convert', $b) }}" class="row g-2" novalidate>
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Priority') }}</label>
                                <select name="priority" class="form-select">@foreach (\App\Enums\LeadPriority::values() as $p)<option value="{{ $p }}" @selected($p === 'Medium')>{{ __($p) }}</option>@endforeach</select>
                            </div>
                            @unless ($b->do_not_contact)
                                <div class="col-7 col-md-5"><label class="form-label small">{{ __('First call') }}</label><input type="date" name="next_followup_date" min="{{ today()->toDateString() }}" class="form-control"></div>
                                <div class="col-5 col-md-3"><label class="form-label small">{{ __('Time') }}</label><input type="time" name="next_followup_time" value="10:00" class="form-control"></div>
                            @endunless
                            <div class="col-12">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="on_duplicate" value="link" id="conv_link"><label class="form-check-label small" for="conv_link">{{ __('If the phone already belongs to a lead, link to it instead of stopping') }}</label></div>
                            </div>
                            <div class="col-12"><button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>{{ __('Save to CRM') }}</button></div>
                        </form>
                    @else
                        <p class="text-muted mb-0">{{ __('Not saved to CRM.') }}</p>
                    @endif
                </div>
            </div>

            {{-- Contact verification --}}
            @if ($isOwner)
                <div class="card">
                    <div class="card-header"><i class="bi bi-telephone-inbound me-1"></i>{{ __('Contact verification') }}</div>
                    <form method="POST" action="{{ route('businesses.verify-contact', $b) }}" class="card-body" novalidate>
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Verification status') }}</label>
                                <select name="verification_status" class="form-select">
                                    @foreach (\App\Enums\ContactStatus::values() as $c)<option value="{{ $c }}" @selected(old('verification_status', $b->contact_status->value) === $c)>{{ __($c) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Verification date') }}</label>
                                <input type="date" name="verification_date" value="{{ old('verification_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone number') }}</label>
                                <input type="tel" name="phone" value="{{ old('phone', $b->phone) }}" class="form-control @error('phone') is-invalid @enderror" maxlength="20">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Contact person') }}</label>
                                <input type="text" name="contact_person" value="{{ old('contact_person', $b->contact_person) }}" class="form-control" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $b->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Contact source') }}</label>
                                <input type="text" name="contact_source" value="{{ old('contact_source', $b->contact_source) }}" class="form-control" placeholder="{{ __('e.g. Called the listed number') }}" maxlength="100">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Source URL') }}</label>
                                <input type="url" name="source_url" value="{{ old('source_url', $b->source_url) }}" class="form-control @error('source_url') is-invalid @enderror">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Notes') }}</label>
                                <textarea name="notes" rows="2" class="form-control" placeholder="{{ __('e.g. Owner available after 5 pm') }}">{{ old('notes') }}</textarea>
                            </div>
                            <div class="col-12 d-flex align-items-center gap-2">
                                <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>{{ __('"Do not contact" cancels the pending follow-up and blocks new ones.') }}</div>
                                <button class="btn btn-success ms-auto"><i class="bi bi-patch-check me-1"></i>{{ __('Save verification') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection

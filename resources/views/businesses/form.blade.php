@extends('layouts.app')
@php $editing = $business->exists; $b = $business; @endphp
@section('title', $editing ? __('Edit business') : __('Add Business to CRM'))
@push('scripts-before')
    <script>window.CRM_DISTRICTS = @json(config('business_finder.districts'));</script>
@endpush
@section('content')
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ $editing ? route('businesses.show', $b) : route('businesses.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-title">{{ $editing ? __('Edit business') : __('Add Business to CRM') }}</h1>
    </div>
    @if ($search)
        <div class="alert alert-light border py-2 small"><i class="bi bi-search me-1"></i>{{ __('From search') }}: <strong>{{ $search->query }}</strong></div>
    @endif

    <form method="POST" action="{{ $editing ? route('businesses.update', $b) : route('businesses.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($search)<input type="hidden" name="business_search_id" value="{{ $search->id }}">@endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-building me-1"></i>{{ __('Business') }}</div>
                    <div class="card-body">
                        <div id="duplicate-box" data-url="{{ route('finder.check-duplicate') }}" @if ($editing) data-except="{{ $b->id }}" @endif></div>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="business_name">{{ __('Business name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" id="business_name" value="{{ old('business_name', $b->business_name) }}" class="form-control @error('business_name') is-invalid @enderror" required maxlength="150">
                                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="category">{{ __('Business category') }}</label>
                                <select name="category" id="category" class="form-select @error('category') is-invalid @enderror">
                                    @foreach (\App\Support\Categories::assignable($b->category) as $c)<option value="{{ $c }}" @selected(old('category', $b->category) === $c)>{{ \App\Support\Categories::label($c) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">{{ __('Public business phone') }}</label>
                                <input type="tel" name="phone" id="phone" value="{{ old('phone', $b->phone) }}" class="form-control @error('phone') is-invalid @enderror" maxlength="20" placeholder="+91 98765 43210">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contact_person">{{ __('Contact person') }}</label>
                                <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person', $b->contact_person) }}" class="form-control @error('contact_person') is-invalid @enderror" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">{{ __('Email') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $b->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="150">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="source_url">{{ __('Contact source URL') }}</label>
                                <input type="url" name="source_url" id="source_url" value="{{ old('source_url', $b->source_url) }}" class="form-control @error('source_url') is-invalid @enderror" placeholder="{{ __('Google Maps listing link, website page…') }}">
                                @error('source_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address">{{ __('Address') }}</label>
                                <input type="text" name="address" id="address" value="{{ old('address', $b->address) }}" class="form-control" maxlength="500">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="district">{{ __('District') }}</label>
                                <select name="district" id="district" class="form-select" data-district-for="city-list">
                                    <option value="">—</option>
                                    @foreach (array_keys(config('business_finder.districts')) as $d)<option value="{{ $d }}" @selected(old('district', $b->district) === $d)>{{ $d }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="city">{{ __('City') }}</label>
                                <input type="text" name="city" id="city" list="city-list" value="{{ old('city', $b->city) }}" class="form-control" maxlength="80">
                                <datalist id="city-list"></datalist>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="area">{{ __('Area') }}</label>
                                <input type="text" name="area" id="area" value="{{ old('area', $b->area) }}" class="form-control" maxlength="100">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label" for="pincode">{{ __('PIN code') }}</label>
                                <input type="text" name="pincode" id="pincode" value="{{ old('pincode', $b->pincode) }}" class="form-control @error('pincode') is-invalid @enderror" inputmode="numeric" maxlength="6">
                                @error('pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-globe2 me-1"></i>{{ __('Website') }}</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="website_url">{{ __('Website URL') }}</label>
                                <div class="input-group">
                                    <input type="text" name="website_url" id="website_url" value="{{ old('website_url', $b->website_url) }}" class="form-control @error('website_url') is-invalid @enderror" placeholder="example.com">
                                    <button type="button" class="btn btn-outline-success" data-website-check data-require-url data-url="{{ route('finder.verify-website') }}" data-input="#website_url" data-target="#check-result">
                                        <i class="bi bi-activity me-1"></i>{{ __('Check') }}
                                    </button>
                                    @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="website_status">{{ __('Website status') }}</label>
                                <select name="website_status" id="website_status" class="form-select">
                                    @foreach (\App\Enums\WebsiteStatus::values() as $w)<option value="{{ $w }}" @selected(old('website_status', $b->website_status?->value) === $w)>{{ __($w) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12" id="check-result"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="bi bi-journal-text me-1"></i>{{ __('Notes') }}</div>
                    <div class="card-body">
                        <textarea name="notes" rows="3" class="form-control" placeholder="{{ __('Anything useful for the call') }}">{{ old('notes', $b->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-patch-check me-1"></i>{{ __('Verification') }}</div>
                    <div class="card-body">
                        <label class="form-label" for="contact_status">{{ __('Contact verification') }}</label>
                        <select name="contact_status" id="contact_status" class="form-select mb-3">
                            @foreach (\App\Enums\ContactStatus::values() as $c)<option value="{{ $c }}" @selected(old('contact_status', $b->contact_status?->value) === $c)>{{ __($c) }}</option>@endforeach
                        </select>
                        <label class="form-label" for="contact_source">{{ __('Contact source') }}</label>
                        <input type="text" name="contact_source" id="contact_source" value="{{ old('contact_source', $b->contact_source) }}" class="form-control mb-2" placeholder="{{ __('e.g. Google Maps listing') }}" maxlength="100">
                        <div class="form-text">{{ __('Only mark "Verified" after you have confirmed the number yourself.') }}</div>
                    </div>
                </div>

                @unless ($editing)
                    <div class="card mb-4 border-success">
                        <div class="card-header"><i class="bi bi-person-plus text-teal me-1"></i>{{ __('Save to CRM') }}</div>
                        <div class="card-body">
                            <input type="hidden" name="save_to_crm" value="0">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="save_to_crm" id="save_to_crm" value="1" @checked(old('save_to_crm', '1') === '1')>
                                <label class="form-check-label" for="save_to_crm">{{ __('Create a CRM lead now') }}</label>
                            </div>
                            <label class="form-label" for="priority">{{ __('Priority') }}</label>
                            <select name="priority" id="priority" class="form-select mb-3">
                                @foreach (\App\Enums\LeadPriority::values() as $p)<option value="{{ $p }}" @selected(old('priority', 'Medium') === $p)>{{ __($p) }}</option>@endforeach
                            </select>
                            <label class="form-label">{{ __('Schedule first call') }}</label>
                            <div class="row g-2 mb-3">
                                <div class="col-7"><input type="date" name="next_followup_date" value="{{ old('next_followup_date') }}" min="{{ today()->toDateString() }}" class="form-control @error('next_followup_date') is-invalid @enderror" aria-label="{{ __('Date') }}"></div>
                                <div class="col-5"><input type="time" name="next_followup_time" value="{{ old('next_followup_time', '10:00') }}" class="form-control" aria-label="{{ __('Time') }}"></div>
                                @error('next_followup_date')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="dup-link-option {{ session('duplicate.can_link') || old('on_duplicate') === 'link' ? '' : 'd-none' }}">
                                <label class="form-label">{{ __('If the phone already belongs to a lead') }}</label>
                                <div class="form-check"><input class="form-check-input" type="radio" name="on_duplicate" id="dup_reject" value="reject" @checked(old('on_duplicate', 'reject') === 'reject')><label class="form-check-label" for="dup_reject">{{ __('Stop and let me review') }}</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="on_duplicate" id="dup_link" value="link" @checked(old('on_duplicate') === 'link')><label class="form-check-label" for="dup_link">{{ __('Link to existing lead') }}</label></div>
                            </div>
                        </div>
                    </div>
                @endunless

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>{{ $editing ? __('Save changes') : __('Add Business to CRM') }}</button>
                    <a href="{{ $editing ? route('businesses.show', $b) : route('businesses.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection

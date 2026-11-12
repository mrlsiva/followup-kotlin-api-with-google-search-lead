@extends('layouts.app')
@php $editing = $lead->exists; @endphp
@section('title', $editing ? __('Edit lead') : __('Add lead'))
@section('content')
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ $editing ? route('leads.show', $lead) : route('leads.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-title">{{ $editing ? __('Edit lead') : __('Add lead') }}</h1>
    </div>

    <form method="POST" action="{{ $editing ? route('leads.update', $lead) : route('leads.store') }}" class="card" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">{{ __('Customer name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $lead->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="company">{{ __('Company') }}</label>
                    <input type="text" name="company" id="company" value="{{ old('company', $lead->company) }}" class="form-control @error('company') is-invalid @enderror" maxlength="150">
                    @error('company')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mobile">{{ __('Mobile number') }}</label>
                    <input type="tel" name="mobile" id="mobile" value="{{ old('mobile', $lead->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" placeholder="+91 98765 43210" maxlength="20">
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $lead->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="150">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @foreach ([['source', \App\Enums\LeadSource::values(), 'Lead source'], ['status', \App\Enums\LeadStatus::values(), 'Status'], ['priority', \App\Enums\LeadPriority::values(), 'Priority']] as [$field, $options, $label])
                    <div class="col-md-4">
                        <label class="form-label" for="{{ $field }}">{{ __($label) }}</label>
                        <select name="{{ $field }}" id="{{ $field }}" class="form-select @error($field) is-invalid @enderror">
                            @foreach ($options as $v)<option value="{{ $v }}" @selected(old($field, $lead->$field?->value) === $v)>{{ __($v) }}</option>@endforeach
                        </select>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="col-12">
                    <label class="form-label" for="notes">{{ __('Notes') }}</label>
                    <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $lead->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><hr class="my-1"></div>
                <div class="col-12">
                    <div class="fw-semibold mb-1"><i class="bi bi-calendar-event text-orange me-1"></i>{{ __('Next follow-up') }}</div>
                    @if ($lead->do_not_contact)
                        <div class="alert alert-dark py-2 mb-0">{{ __('This lead is marked "Do not contact". Follow-ups cannot be scheduled.') }}</div>
                    @else
                        <div class="form-text mb-2">{{ __('Leave empty for no reminder.') }}</div>
                        <div class="row g-2">
                            <div class="col-7 col-md-4">
                                <input type="date" name="next_followup_date" value="{{ old('next_followup_date', $lead->next_followup_at?->format('Y-m-d')) }}" min="{{ today()->toDateString() }}" class="form-control @error('next_followup_date') is-invalid @enderror" aria-label="{{ __('Date') }}">
                                @error('next_followup_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-5 col-md-3">
                                <input type="time" name="next_followup_time" value="{{ old('next_followup_time', $lead->next_followup_at?->format('H:i') ?? '10:00') }}" class="form-control" aria-label="{{ __('Time') }}">
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex gap-2 justify-content-end">
            <a href="{{ $editing ? route('leads.show', $lead) : route('leads.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editing ? __('Save changes') : __('Add lead') }}</button>
        </div>
    </form>
@endsection

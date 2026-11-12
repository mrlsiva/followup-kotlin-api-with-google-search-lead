@extends('layouts.app')
@php $editing = $managed->exists; @endphp
@section('title', $editing ? __('Edit user') : __('Add user'))
@section('content')
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.users.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-title">{{ $editing ? __('Edit user') : __('Add user') }}</h1>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.users.update', $managed) : route('admin.users.store') }}" class="card" style="max-width: 760px" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $managed->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">{{ __('Email') }} <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email', $managed->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="off">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="role">{{ __('Role') }}</label>
                    <select name="role" id="role" class="form-select @error('role') is-invalid @enderror">
                        @foreach (\App\Enums\UserRole::cases() as $r)<option value="{{ $r->value }}" @selected(old('role', $managed->role?->value ?? 'sales') === $r->value)>{{ __($r->label()) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="manager_id">{{ __('Manager (for sales)') }}</label>
                    <select name="manager_id" id="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                        <option value="">{{ __('No manager') }}</option>
                        @foreach ($managers as $m)<option value="{{ $m->id }}" @selected((int) old('manager_id', $managed->manager_id) === $m->id)>{{ $m->name }}</option>@endforeach
                    </select>
                    @error('manager_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="locale">{{ __('Language') }}</label>
                    <select name="locale" id="locale" class="form-select">
                        @foreach (config('crm.locales') as $code => $label)<option value="{{ $code }}" @selected(old('locale', $managed->locale ?? 'en') === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">{{ $editing ? __('New password') : __('Password') }} @unless ($editing)<span class="text-danger">*</span>@endunless</label>
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @unless ($editing) required @endunless>
                    @if ($editing)<div class="form-text">{{ __('Leave empty to keep the current password. Changing it signs the user out of the mobile app.') }}</div>@endif
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1" @checked(old('is_active', $managed->exists ? (int) $managed->is_active : 1) == 1)>
                        <label class="form-check-label" for="is_active">{{ __('Active (can log in to the web panel and the app)') }}</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editing ? __('Save changes') : __('Add user') }}</button>
        </div>
    </form>
@endsection

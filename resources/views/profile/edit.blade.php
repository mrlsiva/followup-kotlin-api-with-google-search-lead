@extends('layouts.app')
@section('title', __('My profile'))
@section('content')
    @php $u = auth()->user(); @endphp
    <h1 class="page-title mb-3">{{ __('My profile') }}</h1>
    <form method="POST" action="{{ route('profile.update') }}" class="card" style="max-width: 640px" novalidate>
        @csrf @method('PUT')
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">{{ __('Name') }}</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $u->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $u->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="locale">{{ __('Language') }}</label>
                    <select name="locale" id="locale" class="form-select">
                        @foreach (config('crm.locales') as $code => $label)<option value="{{ $code }}" @selected(old('locale', $u->locale) === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('Role') }}</label>
                    <div class="form-control-plaintext"><x-badge :value="$u->role" /> @if ($u->manager)<span class="small text-muted ms-1">{{ __('Manager') }}: {{ $u->manager->name }}</span>@endif</div>
                </div>
                <div class="col-12"><hr class="my-1"><div class="fw-semibold">{{ __('Change password') }}</div><div class="form-text">{{ __('Leave empty to keep the current password.') }}</div></div>
                <div class="col-md-4">
                    <label class="form-label" for="current_password">{{ __('Current password') }}</label>
                    <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="password">{{ __('New password') }}</label>
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save changes') }}</button></div>
    </form>
@endsection

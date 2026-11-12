@extends('layouts.guest')
@section('title', __('Log in'))
@section('content')
    <h1 class="h4 fw-bold mb-1">{{ __('Welcome back') }}</h1>
    <p class="text-muted mb-4">{{ __('Log in to your CRM account') }}</p>

    <form method="POST" action="{{ url('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('Email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control form-control-lg @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('Password') }}</label>
            <input type="password" name="password" id="password" class="form-control form-control-lg @error('password') is-invalid @enderror" required autocomplete="current-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
        </div>
        <button class="btn btn-primary btn-lg w-100">{{ __('Log in') }}</button>
    </form>

    @if (config('crm.allow_registration'))
        <p class="text-center text-muted small mt-4 mb-0">{{ __('No account?') }} <a href="{{ route('register') }}">{{ __('Create one') }}</a></p>
    @else
        <p class="text-center text-muted small mt-4 mb-0">{{ __('Need an account? Ask your administrator.') }}</p>
    @endif
@endsection

@extends('layouts.guest')
@section('title', __('Create account'))
@section('content')
    <h1 class="h4 fw-bold mb-4">{{ __('Create account') }}</h1>
    <form method="POST" action="{{ url('register') }}" novalidate>
        @csrf
        @foreach ([['name', 'text', 'Name', 'name'], ['email', 'email', 'Email', 'username']] as [$f, $type, $label, $ac])
            <div class="mb-3">
                <label class="form-label" for="{{ $f }}">{{ __($label) }}</label>
                <input type="{{ $type }}" name="{{ $f }}" id="{{ $f }}" value="{{ old($f) }}" class="form-control @error($f) is-invalid @enderror" required autocomplete="{{ $ac }}">
                @error($f)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('Password') }}</label>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label" for="password_confirmation">{{ __('Confirm password') }}</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100">{{ __('Create account') }}</button>
    </form>
    <p class="text-center text-muted small mt-4 mb-0"><a href="{{ route('login') }}">{{ __('Back to log in') }}</a></p>
@endsection

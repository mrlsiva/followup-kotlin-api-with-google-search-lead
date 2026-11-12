@foreach (['success' => ['success', 'check-circle'], 'error' => ['danger', 'exclamation-octagon'], 'info' => ['info', 'info-circle']] as $key => [$cls, $icon])
    @if (session($key))
        <div class="alert alert-{{ $cls }} alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-{{ $icon }} mt-1"></i>
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! ($hideErrorSummary ?? false))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Please check the form.') }}</div>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
@endif
@if (session('duplicate'))
    @php $dup = session('duplicate'); @endphp
    <div class="alert alert-warning" role="alert">
        <div class="fw-semibold"><i class="bi bi-files me-1"></i>{{ __($dup['message']) }}</div>
        @if ($dup['business'])
            <div class="mt-1">{{ __('Existing business:') }} <a href="{{ $dup['business']['url'] }}">{{ $dup['business']['name'] }}</a></div>
        @endif
        @if ($dup['lead'])
            <div class="mt-1">{{ __('Existing CRM lead:') }} <a href="{{ $dup['lead']['url'] }}">{{ $dup['lead']['name'] }}@if ($dup['lead']['company']) · {{ $dup['lead']['company'] }}@endif</a></div>
        @endif
        @if ($dup['can_link'])
            <div class="small mt-2">{{ __('Review the existing lead, or choose "Link to existing lead" and save again to attach this business to it.') }}</div>
        @endif
    </div>
@endif

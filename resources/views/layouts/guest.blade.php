<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Sling CRM</title>
    <link rel="icon" href="{{ asset('images/sling-icon.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Tamil:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/crm.css') }}?v={{ filemtime(public_path('css/crm.css')) }}" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <div class="w-100" style="max-width: 420px">
        <div class="text-center mb-4">
            <img src="{{ asset('images/sling-logo.png') }}" alt="Sling Software" class="auth-logo">
        </div>
        <div class="card auth-card">
            <div class="card-body p-4 p-sm-5">
                @yield('content')
            </div>
        </div>
        <div class="text-center mt-3">
            @foreach (config('crm.locales') as $code => $label)
                <a href="{{ route('locale', $code) }}" class="link-light text-decoration-none mx-2 small {{ app()->getLocale() === $code ? 'fw-bold' : 'opacity-75' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</div>
</body>
</html>

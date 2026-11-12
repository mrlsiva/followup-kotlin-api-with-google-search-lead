<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Dashboard')) · Sling CRM</title>
    <link rel="icon" href="{{ asset('images/sling-icon.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Tamil:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/crm.css') }}?v={{ filemtime(public_path('css/crm.css')) }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php
    $me = auth()->user();
    $nav = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
@endphp

{{-- Sidebar: fixed on large screens, slide-in menu on phones --}}
<aside class="sl-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="{{ __('Menu') }}">
    <div class="sl-brand">
        <a href="{{ route('dashboard') }}"><img src="{{ asset('images/sling-logo.png') }}" alt="Sling Software"></a>
        <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Close') }}"></button>
    </div>
    <nav class="sl-nav nav flex-column">
        <a class="nav-link {{ $nav('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i>{{ __('Dashboard') }}</a>

        <div class="sl-section">{{ __('CRM') }}</div>
        <a class="nav-link {{ $nav('leads.*') }}" href="{{ route('leads.index') }}"><i class="bi bi-people"></i>{{ __('Leads') }}</a>
        <a class="nav-link {{ $nav('followups.*') }}" href="{{ route('followups.index') }}"><i class="bi bi-telephone-outbound"></i>{{ __('Follow-ups') }}
            @if ($dueCount ?? 0)<span class="badge rounded-pill text-bg-danger">{{ $dueCount }}</span>@endif
        </a>
        <a class="nav-link {{ $nav('transfer.*') }}" href="{{ route('transfer.index') }}"><i class="bi bi-arrow-down-up"></i>{{ __('Import / Export') }}</a>

        <div class="sl-section">{{ __('Business Finder') }}</div>
        <a class="nav-link {{ $nav('finder.dashboard') }}" href="{{ route('finder.dashboard') }}"><i class="bi bi-compass"></i>{{ __('Finder dashboard') }}</a>
        <a class="nav-link {{ $nav('finder.search', 'finder.results') }}" href="{{ route('finder.search') }}"><i class="bi bi-search"></i>{{ __('Search businesses') }}</a>
        <a class="nav-link {{ $nav('businesses.create') }}" href="{{ route('businesses.create') }}"><i class="bi bi-building-add"></i>{{ __('Add business') }}</a>
        <a class="nav-link {{ request()->routeIs('businesses.*') && ! request()->routeIs('businesses.create') ? 'active' : '' }}" href="{{ route('businesses.index') }}"><i class="bi bi-buildings"></i>{{ __('Saved businesses') }}</a>
        <a class="nav-link {{ $nav('finder.history') }}" href="{{ route('finder.history') }}"><i class="bi bi-clock-history"></i>{{ __('Search history') }}</a>

        @if ($me->canViewTeam())
            <div class="sl-section">{{ __('Team') }}</div>
            <a class="nav-link {{ $nav('reports.*') }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart-line"></i>{{ __('Team reports') }}</a>
        @endif
        @if ($me->isAdmin())
            <div class="sl-section">{{ __('Admin') }}</div>
            <a class="nav-link {{ $nav('admin.users.*') }}" href="{{ route('admin.users.index') }}"><i class="bi bi-person-gear"></i>{{ __('Users') }}</a>
            <a class="nav-link {{ $nav('admin.categories.*') }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i>{{ __('Categories') }}</a>
            <a class="nav-link" href="{{ url('api/documentation') }}" target="_blank"><i class="bi bi-braces"></i>{{ __('API docs') }}</a>
        @endif
    </nav>
</aside>

<div class="sl-main">
    <header class="sl-topbar">
        <div class="container-fluid d-flex align-items-center gap-2 py-2 px-3">
            <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Menu') }}">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a class="d-lg-none" href="{{ route('dashboard') }}"><img src="{{ asset('images/sling-icon.jpg') }}" alt="Sling" height="30" class="rounded"></a>

            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="{{ route('businesses.create') }}" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i>{{ __('Add business') }}
                </a>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-label="{{ __('Language') }}">
                        <i class="bi bi-translate"></i> <span class="d-none d-sm-inline">{{ config('crm.locales')[app()->getLocale()] ?? 'English' }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (config('crm.locales') as $code => $label)
                            <li><a class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" href="{{ route('locale', $code) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-semibold" style="width:28px;height:28px;background:var(--sl-teal)">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span>
                        <span class="d-none d-md-inline text-start lh-sm"><span class="d-block">{{ $me->name }}</span><small class="text-muted">{{ __($me->role->label()) }}</small></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('My profile') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Log out') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main class="container-fluid px-3 px-md-4 py-4">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

@php
    $jsStrings = collect([
        'Checking…', 'Check failed.', 'SSL problem', 'cached', 'Could not run the check. Try again.',
        'This phone number is already in your Business Finder list:', 'A CRM lead with this phone number already exists:',
        'Choose "Link to existing lead" below to avoid a duplicate.', 'Similar business already researched:',
    ])->mapWithKeys(fn ($s) => [$s => __($s)]);
@endphp
<script>window.CRM_I18N = {!! json_encode($jsStrings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};</script>
@stack('scripts-before')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@stack('scripts')
</body>
</html>

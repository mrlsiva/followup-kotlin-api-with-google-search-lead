@extends('layouts.app')
@section('title', __('Categories'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h1 class="page-title">{{ __('Business categories') }}</h1>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-plus-lg me-1"></i>{{ __('Add category') }}</a>
    </div>
    <p class="text-muted">{{ __('Categories shown in Business Finder (web and mobile app). Changes apply immediately.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th style="width:70px">{{ __('Order') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Tamil name') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Google search words') }}</th>
                    <th class="text-end d-none d-sm-table-cell">{{ __('Businesses') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($categories as $c)
                    @php $used = (int) ($usage[$c->name] ?? 0); @endphp
                    <tr class="{{ $c->is_active ? '' : 'opacity-50' }}">
                        <td class="text-muted">{{ $c->sort_order }}</td>
                        <td>
                            <span class="fw-semibold">{{ $c->name }}</span>
                            @if ($c->isSystem())<span class="badge bg-light text-muted ms-1" title="{{ __('Built-in categories cannot be renamed or removed.') }}">{{ __('Built-in') }}</span>@endif
                        </td>
                        <td class="d-none d-md-table-cell">{{ $c->name_ta ?: '—' }}</td>
                        <td class="d-none d-lg-table-cell small text-muted">“{{ $c->term() }} in Chennai”</td>
                        <td class="text-end d-none d-sm-table-cell">@if ($used)<a href="{{ route('businesses.index', ['category' => $c->name]) }}">{{ $used }}</a>@else 0 @endif</td>
                        <td>@if ($c->is_active)<span class="badge bg-teal-subtle text-teal">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Off') }}</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.categories.edit', $c) }}" class="btn btn-sm btn-light" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @unless ($c->isSystem())
                                <form method="POST" action="{{ route('admin.categories.toggle', $c) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-light {{ $c->is_active ? 'text-warning-emphasis' : 'text-success' }}" title="{{ $c->is_active ? __('Turn off') : __('Turn on') }}"><i class="bi bi-{{ $c->is_active ? 'eye-slash' : 'eye' }}"></i></button>
                                </form>
                                @if (! $used)
                                    <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete this category?') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light text-danger" title="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <ul class="small text-muted mt-3">
        <li>{{ __('Turning a category off hides it from new searches and new businesses. Businesses already saved with it keep it.') }}</li>
        <li>{{ __('Renaming a category also updates the businesses and search history that use it.') }}</li>
        <li>{{ __('A category can only be deleted when no business uses it.') }}</li>
    </ul>
@endsection

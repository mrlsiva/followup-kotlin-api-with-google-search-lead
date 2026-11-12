@extends('layouts.app')
@section('title', __('Follow-ups'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="page-title">{{ __('Follow-ups') }}</h1>
        <form class="ms-auto" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="input-group input-group-sm">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, company or phone') }}">
                <button class="btn btn-success"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>

    <ul class="nav nav-pills mb-3 gap-1 flex-nowrap overflow-auto pb-1">
        @foreach (['today' => ['Today', 'calendar-check'], 'overdue' => ['Overdue', 'alarm'], 'upcoming' => ['Upcoming', 'calendar-week'], 'pending' => ['All pending', 'hourglass-split'], 'completed' => ['Completed', 'check2-all']] as $key => [$label, $icon])
            <li class="nav-item">
                <a class="nav-link text-nowrap {{ $type === $key ? 'active bg-success' : 'text-body' }}" href="{{ route('followups.index', ['type' => $key]) }}">
                    <i class="bi bi-{{ $icon }} me-1"></i>{{ __($label) }}
                    @isset($counts[$key])<span class="badge rounded-pill {{ $key === 'overdue' && $counts[$key] ? 'text-bg-danger' : 'text-bg-light' }} ms-1">{{ $counts[$key] }}</span>@endisset
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ $type === 'completed' ? __('Called on') : __('Due') }}</th>
                    <th>{{ __('Lead') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Phone') }}</th>
                    <th class="d-none d-lg-table-cell">{{ $type === 'completed' ? __('Result') : __('Priority') }}</th>
                    @if ($type === 'completed')<th class="d-none d-md-table-cell">{{ __('Note') }}</th>@endif
                    <th class="text-end">{{ $type === 'completed' ? '' : __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($followups as $f)
                    <tr class="{{ $f->isOverdue() ? 'row-overdue' : '' }}">
                        <td class="text-nowrap">
                            <div class="fw-semibold {{ $f->isOverdue() ? 'text-overdue' : '' }}">{{ ($f->completed_at ?? $f->scheduled_at)->format('h:i A') }}</div>
                            <div class="small text-muted">{{ ($f->completed_at ?? $f->scheduled_at)->format('D, d M') }}</div>
                        </td>
                        <td>
                            <a href="{{ route('leads.show', $f->lead_id) }}" class="fw-semibold text-decoration-none">{{ $f->lead?->name }}</a>
                            <div class="small text-muted">{{ $f->lead?->company }}</div>
                        </td>
                        <td class="d-none d-md-table-cell">@if ($f->lead?->mobile)<a href="tel:{{ $f->lead->mobile }}" class="text-decoration-none"><i class="bi bi-telephone me-1"></i>{{ $f->lead->mobile }}</a>@endif</td>
                        <td class="d-none d-lg-table-cell">@if ($type === 'completed')<x-badge :value="$f->call_status" />@else<x-badge :value="$f->lead?->priority" />@endif</td>
                        @if ($type === 'completed')<td class="d-none d-md-table-cell small text-muted" style="max-width:320px">{{ \Illuminate\Support\Str::limit($f->note, 90) }}</td>@endif
                        <td class="text-end">@include('partials.followup-row-actions', ['f' => $f])</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar2-check"></i>{{ __('Nothing here.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $followups->links() }}</div>

    @include('partials.followup-modals')
@endsection

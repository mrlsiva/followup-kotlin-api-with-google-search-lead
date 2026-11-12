@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <div>
            <h1 class="page-title">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</h1>
            <div class="text-muted small">{{ now()->translatedFormat('l, d F Y') }}</div>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('leads.create') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('Add lead') }}</a>
            <a href="{{ route('finder.search') }}" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>{{ __('Find businesses') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl">@include('partials.stat', ['label' => __('Total leads'), 'value' => $stats['total_leads'], 'icon' => 'people', 'tone' => 'navy', 'href' => route('leads.index', ['owner' => 'me'])])</div>
        <div class="col-6 col-xl">@include('partials.stat', ['label' => __('Due today'), 'value' => $stats['today'], 'icon' => 'calendar-check', 'tone' => 'orange', 'href' => route('followups.index', ['type' => 'today'])])</div>
        <div class="col-6 col-xl">@include('partials.stat', ['label' => __('Overdue'), 'value' => $stats['overdue'], 'icon' => 'alarm', 'tone' => 'red', 'href' => route('followups.index', ['type' => 'overdue'])])</div>
        <div class="col-6 col-xl">@include('partials.stat', ['label' => __('Pending'), 'value' => $stats['pending'], 'icon' => 'hourglass-split', 'tone' => 'navy', 'href' => route('followups.index', ['type' => 'pending'])])</div>
        <div class="col-12 col-xl">@include('partials.stat', ['label' => __('Calls done'), 'value' => $stats['completed'], 'icon' => 'telephone-outbound', 'tone' => 'teal', 'href' => route('followups.index', ['type' => 'completed'])])</div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <span><i class="bi bi-calendar-check text-orange me-2"></i>{{ __("Today's follow-ups") }}</span>
                    <a href="{{ route('followups.index') }}" class="ms-auto small">{{ __('View all') }}</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($overdue->merge($today)->unique('id') as $f)
                        <div class="list-group-item d-flex align-items-center gap-3 {{ $f->isOverdue() ? 'bg-danger-subtle bg-opacity-25' : '' }}">
                            <div class="text-center" style="min-width:64px">
                                <div class="fw-semibold {{ $f->isOverdue() ? 'text-overdue' : '' }}">{{ $f->scheduled_at->format('h:i A') }}</div>
                                <div class="small text-muted">{{ $f->scheduled_at->isToday() ? __('Today') : $f->scheduled_at->format('d M') }}</div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('leads.show', $f->lead_id) }}" class="fw-semibold text-decoration-none">{{ $f->lead?->name }}</a>
                                <div class="small text-muted text-truncate">{{ $f->lead?->company }} @if ($f->lead?->mobile) · <a href="tel:{{ $f->lead->mobile }}">{{ $f->lead->mobile }}</a>@endif</div>
                            </div>
                            @if ($f->isOverdue())<span class="badge text-bg-danger d-none d-sm-inline">{{ __('Overdue') }}</span>@endif
                            @include('partials.followup-row-actions', ['f' => $f])
                        </div>
                    @empty
                        <div class="empty-state"><i class="bi bi-cup-hot"></i>{{ __('No follow-ups due today.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <span><i class="bi bi-people text-teal me-2"></i>{{ __('Recent leads') }}</span>
                    <a href="{{ route('leads.index') }}" class="ms-auto small">{{ __('View all') }}</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                        @forelse ($recentLeads as $lead)
                            <tr>
                                <td><a href="{{ route('leads.show', $lead) }}" class="fw-semibold text-decoration-none">{{ $lead->name }}</a><div class="small text-muted">{{ $lead->company }}</div></td>
                                <td class="d-none d-sm-table-cell small">{{ __($lead->source->value) }}</td>
                                <td><x-badge :value="$lead->status" /></td>
                                <td class="text-end small text-muted">{{ $lead->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty-state"><i class="bi bi-person-plus"></i>{{ __('No leads yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-funnel text-teal me-2"></i>{{ __('Pipeline') }}</div>
                <div class="card-body">
                    @php $max = max(1, $byStatus->max()); @endphp
                    @foreach ($byStatus as $status => $n)
                        <a href="{{ route('leads.index', ['status' => $status, 'owner' => 'me']) }}" class="d-block text-decoration-none text-body mb-2">
                            <div class="d-flex justify-content-between small mb-1"><span>{{ __($status) }}</span><span class="fw-semibold">{{ $n }}</span></div>
                            <div class="progress" style="height:8px"><div class="progress-bar {{ in_array($status, ['Won', 'Interested']) ? 'bg-success' : ($status === 'Lost' ? 'bg-danger' : 'bg-primary') }}" style="width: {{ $n * 100 / $max }}%"></div></div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <span><i class="bi bi-compass text-orange me-2"></i>{{ __('Business Finder') }}</span>
                    <a href="{{ route('finder.dashboard') }}" class="ms-auto small">{{ __('Open') }}</a>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        @foreach ([['total_researched', 'Researched'], ['saved_to_crm', 'Saved to CRM'], ['no_website', 'No website'], ['contact_verified', 'Verified contacts'], ['interested', 'Interested'], ['converted', 'Converted']] as [$k, $label])
                            <div class="col-4"><div class="border rounded-3 py-2"><div class="fs-5 fw-bold">{{ $finder[$k] }}</div><div class="small text-muted">{{ __($label) }}</div></div></div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($team)
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span><i class="bi bi-bar-chart-line text-teal me-2"></i>{{ __('Team this month') }}</span>
                        <a href="{{ route('reports.index') }}" class="ms-auto small">{{ __('Full report') }}</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>{{ __('Salesperson') }}</th><th class="text-end">{{ __('Calls') }}</th><th class="text-end">{{ __('Won') }}</th><th class="text-end">{{ __('Overdue') }}</th></tr></thead>
                            <tbody>
                            @foreach ($team['rows']->take(6) as $r)
                                <tr><td>{{ $r['user']->name }}</td><td class="text-end">{{ $r['calls'] }}</td><td class="text-end">{{ $r['won'] }}</td><td class="text-end {{ $r['overdue'] ? 'text-overdue' : '' }}">{{ $r['overdue'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('partials.followup-modals')
@endsection

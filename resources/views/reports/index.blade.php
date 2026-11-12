@extends('layouts.app')
@section('title', __('Team reports'))
@section('content')
    @php $q = request()->only(['period', 'from', 'to', 'user']); @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title">{{ __('Team reports') }}</h1>
            <div class="text-muted small">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('reports.export', ['format' => 'excel'] + $q) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            <a href="{{ route('reports.export', ['format' => 'pdf'] + $q) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        </div>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-auto">
                <div class="btn-group flex-wrap" role="group">
                    @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom'] as $k => $l)
                        <input type="radio" class="btn-check" name="period" id="p_{{ $k }}" value="{{ $k }}" @checked($period === $k) onchange="if(this.value!=='custom')this.form.submit()">
                        <label class="btn btn-outline-success btn-sm" for="p_{{ $k }}">{{ __($l) }}</label>
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ __('From') }}</label><input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ __('To') }}</label><input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" class="form-control form-control-sm"></div>
            <div class="col-8 col-md-3">
                <select name="user" class="form-select form-select-sm">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-4 col-md-auto"><button class="btn btn-success btn-sm w-100">{{ __('Apply') }}</button></div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-xl">@include('partials.stat', ['label' => __('Leads added'), 'value' => $totals['leads_added'], 'icon' => 'person-plus', 'tone' => 'navy'])</div>
        <div class="col-6 col-md-3 col-xl">@include('partials.stat', ['label' => __('Calls done'), 'value' => $totals['calls'], 'icon' => 'telephone-outbound', 'tone' => 'teal'])</div>
        <div class="col-6 col-md-3 col-xl">@include('partials.stat', ['label' => __('Overdue now'), 'value' => $totals['overdue'], 'icon' => 'alarm', 'tone' => 'red'])</div>
        <div class="col-6 col-md-3 col-xl">@include('partials.stat', ['label' => __('Won'), 'value' => $totals['won'], 'icon' => 'trophy', 'tone' => 'orange', 'hint' => __('Conversion').': '.$totals['conversion'].'%'])</div>
        <div class="col-12 col-md-6 col-xl">@include('partials.stat', ['label' => __('Businesses researched'), 'value' => $totals['researched'], 'icon' => 'compass', 'tone' => 'navy', 'hint' => __('Saved to CRM').': '.$totals['saved_to_crm']])</div>
    </div>

    <div class="card mb-4">
        <div class="card-header">{{ __('By salesperson') }}</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>{{ __('Salesperson') }}</th>
                    <th class="text-end">{{ __('Leads added') }}</th><th class="text-end">{{ __('Calls done') }}</th>
                    <th class="text-end d-none d-md-table-cell">{{ __('Pending') }}</th><th class="text-end">{{ __('Overdue') }}</th>
                    <th class="text-end d-none d-lg-table-cell">{{ __('Interested') }}</th><th class="text-end">{{ __('Won') }}</th>
                    <th class="text-end d-none d-lg-table-cell">{{ __('Conversion') }}</th>
                    <th class="text-end d-none d-xl-table-cell">{{ __('Researched') }}</th><th class="text-end d-none d-xl-table-cell">{{ __('Saved to CRM') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr class="{{ $r['user']->is_active ? '' : 'opacity-50' }}">
                        <td><a href="{{ route('leads.index', ['owner' => $r['user']->id]) }}" class="fw-semibold text-decoration-none">{{ $r['user']->name }}</a> <x-badge :value="$r['user']->role" /></td>
                        <td class="text-end">{{ $r['leads_added'] }}</td>
                        <td class="text-end fw-semibold">{{ $r['calls'] }}</td>
                        <td class="text-end d-none d-md-table-cell">{{ $r['pending'] }}</td>
                        <td class="text-end {{ $r['overdue'] ? 'text-overdue' : '' }}">{{ $r['overdue'] }}</td>
                        <td class="text-end d-none d-lg-table-cell">{{ $r['interested'] }}</td>
                        <td class="text-end">{{ $r['won'] }}</td>
                        <td class="text-end d-none d-lg-table-cell">{{ $r['conversion'] }}%</td>
                        <td class="text-end d-none d-xl-table-cell">{{ $r['researched'] }}</td>
                        <td class="text-end d-none d-xl-table-cell">{{ $r['saved_to_crm'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="empty-state">{{ __('No team members yet.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">{{ __('Leads added, calls done and businesses researched are counted in the selected period. Pending, overdue, interested, won and conversion show the current pipeline.') }}</div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-activity me-1"></i>{{ __('Recent activity') }}</div>
        <div class="list-group list-group-flush">
            @forelse ($activity as $a)
                <div class="list-group-item d-flex gap-3 small">
                    <span class="text-muted text-nowrap" style="min-width: 8.5rem">{{ $a->created_at->format('d M, h:i A') }}</span>
                    <span class="fw-semibold text-nowrap">{{ $a->user?->name ?? '—' }}</span>
                    <span>{{ __(\Illuminate\Support\Str::of($a->action)->replace(['_', '.'], ' ')->ucfirst()->toString()) }}
                        @if (! empty($a->details['name']) || ! empty($a->details['business_name']))<span class="text-muted">· {{ $a->details['name'] ?? $a->details['business_name'] }}</span>@endif
                    </span>
                </div>
            @empty
                <div class="empty-state">{{ __('No activity yet.') }}</div>
            @endforelse
        </div>
    </div>
@endsection

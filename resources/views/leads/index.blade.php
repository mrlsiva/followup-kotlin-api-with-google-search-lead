@extends('layouts.app')
@section('title', __('Leads'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="page-title">{{ __('Leads') }} <span class="text-muted fs-6 fw-normal">({{ $leads->total() }})</span></h1>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('transfer.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-down-up me-1"></i>{{ __('Import / Export') }}</a>
            <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add lead') }}</a>
        </div>
    </div>

    <form class="card card-body mb-3" method="GET">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, company, phone or email') }}">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select" data-autosubmit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\LeadStatus::values() as $v)<option value="{{ $v }}" @selected(request('status') === $v)>{{ __($v) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="priority" class="form-select" data-autosubmit>
                    <option value="">{{ __('All priorities') }}</option>
                    @foreach (\App\Enums\LeadPriority::values() as $v)<option value="{{ $v }}" @selected(request('priority') === $v)>{{ __($v) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="source" class="form-select" data-autosubmit>
                    <option value="">{{ __('All sources') }}</option>
                    @foreach (\App\Enums\LeadSource::values() as $v)<option value="{{ $v }}" @selected(request('source') === $v)>{{ __($v) }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="sort" class="form-select" data-autosubmit>
                    @foreach (['latest' => 'Recently updated', 'next_followup' => 'Next follow-up', 'priority' => 'Priority', 'name' => 'Name', 'oldest' => 'Oldest'] as $v => $label)
                        <option value="{{ $v }}" @selected(request('sort', 'latest') === $v)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($users->isNotEmpty())
                <div class="col-12 col-md-4">
                    <select name="owner" class="form-select" data-autosubmit>
                        <option value="">{{ auth()->user()->isAdmin() ? __('All salespeople') : __('My team') }}</option>
                        <option value="me" @selected(request('owner') === 'me')>{{ __('Only mine') }}</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('owner') == $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
                <button class="btn btn-success"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                @if (request()->query())<a href="{{ route('leads.index') }}" class="btn btn-light">{{ __('Clear') }}</a>@endif
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>{{ __('Lead') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Phone') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Priority') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Source') }}</th>
                    <th>{{ __('Next follow-up') }}</th>
                    @if ($users->isNotEmpty())<th class="d-none d-xl-table-cell">{{ __('Owner') }}</th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse ($leads as $lead)
                    <tr class="{{ $lead->next_followup_at?->isPast() ? 'row-overdue' : '' }}">
                        <td>
                            <a href="{{ route('leads.show', $lead) }}" class="fw-semibold text-decoration-none">{{ $lead->name }}</a>
                            @if ($lead->do_not_contact)<span class="badge text-bg-dark ms-1">{{ __('Do not contact') }}</span>@endif
                            <div class="small text-muted">{{ $lead->company }}</div>
                        </td>
                        <td class="d-none d-md-table-cell">@if ($lead->mobile)<a href="tel:{{ $lead->mobile }}" class="text-decoration-none">{{ $lead->mobile }}</a>@else<span class="text-muted">—</span>@endif</td>
                        <td><x-badge :value="$lead->status" /></td>
                        <td class="d-none d-lg-table-cell"><x-badge :value="$lead->priority" /></td>
                        <td class="d-none d-lg-table-cell small">{{ __($lead->source->value) }}</td>
                        <td class="small {{ $lead->next_followup_at?->isPast() ? 'text-overdue' : '' }}">
                            {{ $lead->next_followup_at?->format('d M, h:i A') ?? '—' }}
                        </td>
                        @if ($users->isNotEmpty())<td class="d-none d-xl-table-cell small">{{ $lead->user?->name }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>{{ __('No leads match the filters.') }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $leads->links() }}</div>
@endsection

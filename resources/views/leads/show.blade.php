@extends('layouts.app')
@section('title', $lead->name)
@section('content')
    @php $pending = $lead->followups->firstWhere('status', \App\Enums\FollowupStatus::Pending); @endphp

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('leads.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="page-title">{{ $lead->name }}</h1>
            <div class="text-muted small">{{ $lead->company }}</div>
        </div>
        <div class="d-flex flex-wrap gap-1 ms-2">
            <x-badge :value="$lead->status" /><x-badge :value="$lead->priority" />
            @if ($lead->do_not_contact)<span class="badge text-bg-dark">{{ __('Do not contact') }}</span>@endif
        </div>
        @if ($isOwner)
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('leads.destroy', $lead) }}" data-confirm="{{ __('Delete this lead and its pending follow-up?') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        @endif
    </div>

    @unless ($isOwner)
        <div class="alert alert-info py-2"><i class="bi bi-eye me-1"></i>{{ __('You are viewing :name\'s lead (read-only).', ['name' => $lead->user?->name]) }}</div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('Details') }}</div>
                <div class="card-body">
                    <dl class="dl-grid">
                        <dt>{{ __('Mobile') }}</dt>
                        <dd>@if ($lead->mobile)<a href="tel:{{ $lead->mobile }}">{{ $lead->mobile }}</a> @if (strlen((string) $lead->mobile_digits) === 10)<a class="ms-2 text-success" target="_blank" rel="noopener" href="https://wa.me/91{{ $lead->mobile_digits }}" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif@else — @endif</dd>
                        <dt>{{ __('Email') }}</dt><dd>@if ($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else — @endif</dd>
                        <dt>{{ __('Source') }}</dt><dd>{{ __($lead->source->value) }}</dd>
                        <dt>{{ __('Owner') }}</dt><dd>{{ $lead->user?->name }}</dd>
                        <dt>{{ __('Created') }}</dt><dd>{{ $lead->created_at->format('d M Y') }}</dd>
                        <dt>{{ __('Next follow-up') }}</dt>
                        <dd class="{{ $lead->next_followup_at?->isPast() ? 'text-overdue' : '' }}">{{ $lead->next_followup_at?->format('d M Y, h:i A') ?? '—' }}</dd>
                    </dl>
                    @if ($lead->notes)
                        <hr>
                        <div class="small text-muted mb-1">{{ __('Notes') }}</div>
                        <div style="white-space: pre-line">{{ $lead->notes }}</div>
                    @endif
                </div>
            </div>

            @if ($lead->businessLead)
                @php $b = $lead->businessLead; @endphp
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center"><span><i class="bi bi-compass text-orange me-1"></i>{{ __('Business Finder') }}</span>
                        <a href="{{ route('businesses.show', $b) }}" class="ms-auto small">{{ __('Open') }}</a></div>
                    <div class="card-body">
                        <dl class="dl-grid">
                            <dt>{{ __('Category') }}</dt><dd>{{ \App\Support\Categories::label($b->category) }}</dd>
                            <dt>{{ __('Location') }}</dt><dd>{{ collect([$b->area, $b->city, $b->district])->filter()->implode(', ') ?: '—' }}</dd>
                            <dt>{{ __('Website') }}</dt><dd><x-badge :value="$b->website_status" /></dd>
                            <dt>{{ __('Contact') }}</dt><dd><x-badge :value="$b->contact_status" /></dd>
                        </dl>
                    </div>
                </div>
            @endif

            @if ($canReassign)
                <div class="card">
                    <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i>{{ __('Assign to salesperson') }}</div>
                    <form method="POST" action="{{ route('leads.reassign', $lead) }}" class="card-body d-flex gap-2" data-confirm="{{ __('Move this lead and its follow-ups to the selected salesperson?') }}">
                        @csrf
                        <select name="user_id" class="form-select">
                            @foreach ($assignees as $u)<option value="{{ $u->id }}" @selected($u->id === $lead->user_id)>{{ $u->name }}</option>@endforeach
                        </select>
                        <button class="btn btn-outline-success">{{ __('Assign') }}</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            @if ($isOwner && $pending)
                <div class="card mb-4 border-{{ $pending->isOverdue() ? 'danger' : 'warning' }}">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <span class="stat-icon {{ $pending->isOverdue() ? 'bg-danger-subtle text-danger' : 'bg-orange-subtle text-orange' }} rounded-3 p-2 fs-4"><i class="bi bi-alarm"></i></span>
                        <div>
                            <div class="small text-muted">{{ $pending->isOverdue() ? __('Overdue follow-up') : __('Next follow-up') }}</div>
                            <div class="fw-semibold fs-5 {{ $pending->isOverdue() ? 'text-overdue' : '' }}">{{ $pending->scheduled_at->format('D, d M Y · h:i A') }}</div>
                        </div>
                        <div class="ms-auto">@include('partials.followup-row-actions', ['f' => $pending->setRelation('lead', $lead)])</div>
                    </div>
                </div>
            @endif

            @if ($isOwner)
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-journal-plus text-teal me-1"></i>{{ __('Add call note') }}</div>
                    <form method="POST" action="{{ route('leads.notes.store', $lead) }}" class="card-body" novalidate>
                        @csrf
                        <div class="row g-2">
                            <div class="col-6 col-md-3"><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control" aria-label="{{ __('Date') }}"></div>
                            <div class="col-6 col-md-2"><input type="time" name="time" value="{{ old('time', now()->format('H:i')) }}" class="form-control" aria-label="{{ __('Time') }}"></div>
                            <div class="col-12 col-md-4">
                                <select name="call_status" class="form-select" aria-label="{{ __('Status after call') }}">
                                    @foreach (\App\Enums\CallStatus::values() as $cs)<option value="{{ $cs }}" @selected(old('call_status') === $cs)>{{ __($cs) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <select name="schedule_next" class="form-select" aria-label="{{ __('Next follow-up') }}">
                                    <option value="">{{ __('Keep schedule') }}</option>
                                    <option value="1">{{ __('Auto-schedule next') }}</option>
                                    <option value="0">{{ __('No next follow-up') }}</option>
                                </select>
                            </div>
                            <div class="col-12"><textarea name="note" rows="2" class="form-control" placeholder="{{ __('What was discussed?') }}">{{ old('note') }}</textarea></div>
                            <div class="col-12 text-end"><button class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>{{ __('Add note') }}</button></div>
                        </div>
                    </form>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history me-1"></i>{{ __('Timeline') }}</div>
                <div class="card-body">
                    @if ($lead->followups->isEmpty())
                        <div class="empty-state"><i class="bi bi-chat-square-text"></i>{{ __('No calls recorded yet.') }}</div>
                    @else
                        <ul class="timeline">
                            @foreach ($lead->followups as $f)
                                <li class="{{ $f->isPending() ? ($f->isOverdue() ? 'overdue' : 'pending') : '' }}">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="fw-semibold">{{ ($f->completed_at ?? $f->scheduled_at)->format('d M Y, h:i A') }}</span>
                                        @if ($f->isPending())<x-badge value="pending" />@else<x-badge :value="$f->call_status" />@endif
                                        @if ($isOwner && ! $f->isPending())
                                            <form method="POST" action="{{ route('followups.destroy', $f) }}" class="ms-auto" data-confirm="{{ __('Delete this note?') }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-link btn-sm text-muted p-0"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                    @if ($f->note)<div class="text-body-secondary mt-1" style="white-space: pre-line">{{ $f->note }}</div>@endif
                                    @if ($f->isPending())<div class="small text-muted">{{ __('Scheduled call') }}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('partials.followup-modals')
@endsection

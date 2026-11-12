{{-- Complete / reschedule / cancel buttons for a pending follow-up (modals live in partials.followup-modals) --}}
@if ($f->isPending())
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeModal"
                data-action="{{ route('followups.complete', $f) }}" data-fill-lead-label="{{ $f->lead?->name }}{{ $f->lead?->company ? ' · '.$f->lead->company : '' }}">
            <i class="bi bi-check2-circle"></i><span class="d-none d-xl-inline ms-1">{{ __('Done') }}</span>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rescheduleModal"
                data-action="{{ route('followups.reschedule', $f) }}" data-fill-next-followup-date="{{ $f->scheduled_at->format('Y-m-d') }}"
                data-fill-next-followup-time="{{ $f->scheduled_at->format('H:i') }}" title="{{ __('Reschedule') }}">
            <i class="bi bi-calendar-event"></i>
        </button>
    </div>
@endif

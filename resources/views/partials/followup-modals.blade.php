{{-- Shared modals: complete a follow-up (records the call) and reschedule --}}
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Record the call') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" data-fill="lead_label"></p>
                <label class="form-label">{{ __('Status after call') }}</label>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (\App\Enums\CallStatus::values() as $i => $cs)
                        <input type="radio" class="btn-check" name="call_status" id="cs{{ $i }}" value="{{ $cs }}" {{ $i === 0 ? 'checked' : '' }}>
                        <label class="btn btn-outline-success btn-sm" for="cs{{ $i }}">{{ __($cs) }}</label>
                    @endforeach
                </div>
                <label class="form-label" for="complete_note">{{ __('Note') }}</label>
                <textarea class="form-control mb-3" name="note" id="complete_note" rows="3" placeholder="{{ __('What was discussed?') }}"></textarea>

                <input type="hidden" name="schedule_next" value="1">
                <div class="form-text mb-2"><i class="bi bi-magic"></i> {{ __('The next follow-up is scheduled automatically (Not Answered / Callback: +1 day · Interested: +1/+3/+5 days by priority · Closed: none, Sundays skipped). Pick a date to override.') }}</div>
                <div class="row g-2">
                    <div class="col-7"><input type="date" class="form-control" name="next_followup_date" min="{{ today()->toDateString() }}" aria-label="{{ __('Next follow-up date') }}"></div>
                    <div class="col-5"><input type="time" class="form-control" name="next_followup_time" aria-label="{{ __('Time') }}"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Reschedule') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">{{ __('Date') }}</label>
                <input type="date" class="form-control mb-2" name="next_followup_date" min="{{ today()->toDateString() }}" required>
                <label class="form-label">{{ __('Time') }}</label>
                <input type="time" class="form-control" name="next_followup_time" value="10:00">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

<?php

namespace App\Http\Requests;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Http\Requests\Concerns\ParsesNextFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Add a follow-up note to a lead (POST /leads/{lead}/followups) or
 * complete a pending follow-up (POST /followups/{followup}/complete).
 *
 * Optional call details from the phone (direction, number, start/end, duration) and an optional
 * call recording (multipart field `recording`) are stored on the same note.
 */
class FollowupNoteRequest extends FormRequest
{
    use ParsesNextFollowup;

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:5000'],
            'call_status' => ['required', Rule::enum(CallStatus::class)],
            'schedule_next' => ['nullable', 'boolean'],
            ...$this->nextFollowupRules(),

            // Call details (all optional)
            'call_direction' => ['nullable', Rule::enum(CallDirection::class)],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'call_started_at' => ['nullable', 'date'],
            'call_ended_at' => ['nullable', 'date', 'after_or_equal:call_started_at'],
            'call_duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'recording' => ['nullable', ...CallRecordingRequest::fileRules()],
        ];
    }

    public function messages(): array
    {
        return (new CallRecordingRequest)->messages();
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if ($this->noteTime()->isAfter(now()->addMinute())) {
                $v->errors()->add('date', 'The note date/time cannot be in the future.');
            }
            if ($this->filled('call_started_at') && $this->callTime('call_started_at')->isAfter(now()->addMinute())) {
                $v->errors()->add('call_started_at', 'The call start time cannot be in the future.');
            }
        });
    }

    /** When the call happened: date + time if sent, else call_started_at, else now. */
    public function noteTime(): Carbon
    {
        if (! $this->filled('date') && ! $this->filled('time') && $this->filled('call_started_at')) {
            return $this->callTime('call_started_at')->seconds(0);
        }
        $date = $this->input('date') ?: now()->format('Y-m-d');
        $time = $this->input('time') ?: now()->format('H:i');

        return Carbon::createFromFormat('Y-m-d H:i', "$date $time")->seconds(0);
    }

    /** Payload for FollowupService::addNote / complete. */
    public function serviceData(): array
    {
        $timed = $this->filled('date') || $this->filled('time') || $this->filled('call_started_at');

        return [
            'at' => $this->noteTime(),
            'completed_at' => $timed ? $this->noteTime() : null,
            'note' => $this->input('note'),
            'call_status' => CallStatus::from($this->input('call_status')),
            'next_at' => $this->nextFollowupAt(),
            'schedule_next' => $this->has('schedule_next') && $this->input('schedule_next') !== null
                ? $this->boolean('schedule_next') : null,
            'call' => $this->callDetails(),
            'recording' => $this->file('recording') instanceof UploadedFile ? $this->file('recording') : null,
        ];
    }

    /** Call-log columns that were sent (missing ones are left untouched). */
    private function callDetails(): array
    {
        $started = $this->filled('call_started_at') ? $this->callTime('call_started_at') : null;
        $ended = $this->filled('call_ended_at') ? $this->callTime('call_ended_at') : null;
        $duration = $this->filled('call_duration') ? (int) $this->input('call_duration')
            : ($started && $ended ? (int) $started->diffInSeconds($ended) : null);

        return array_filter([
            'call_direction' => $this->filled('call_direction') ? CallDirection::from($this->input('call_direction')) : null,
            'phone_number' => $this->filled('phone_number') ? trim($this->input('phone_number')) : null,
            'call_started_at' => $started,
            'call_ended_at' => $ended,
            'call_duration' => $duration,
        ], fn ($v) => $v !== null);
    }

    /** ISO date-time from the phone, converted to the app's time zone. */
    private function callTime(string $key): Carbon
    {
        return Carbon::parse($this->input($key))->setTimezone(config('app.timezone'));
    }
}

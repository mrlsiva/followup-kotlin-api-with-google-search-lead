<?php

namespace App\Http\Requests;

use App\Enums\CallStatus;
use App\Http\Requests\Concerns\ParsesNextFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Add a follow-up note to a lead (POST /leads/{lead}/followups) or
 * complete a pending follow-up (POST /followups/{followup}/complete).
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
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
        $validator->after(function ($v) {
            if ($v->errors()->isEmpty() && $this->noteTime()->isAfter(now()->addMinute())) {
                $v->errors()->add('date', 'The note date/time cannot be in the future.');
            }
        });
    }

    /** When the call happened (defaults to now). */
    public function noteTime(): Carbon
    {
        $date = $this->input('date') ?: now()->format('Y-m-d');
        $time = $this->input('time') ?: now()->format('H:i');

        return Carbon::createFromFormat('Y-m-d H:i', "$date $time")->seconds(0);
    }

    /** Payload for FollowupService::addNote / complete. */
    public function serviceData(): array
    {
        return [
            'at' => $this->noteTime(),
            'completed_at' => $this->filled('date') || $this->filled('time') ? $this->noteTime() : null,
            'note' => $this->input('note'),
            'call_status' => CallStatus::from($this->input('call_status')),
            'next_at' => $this->nextFollowupAt(),
            'schedule_next' => $this->has('schedule_next') && $this->input('schedule_next') !== null
                ? $this->boolean('schedule_next') : null,
        ];
    }
}

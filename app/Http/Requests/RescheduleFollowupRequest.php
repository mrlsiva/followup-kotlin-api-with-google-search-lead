<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ParsesNextFollowup;
use Illuminate\Foundation\Http\FormRequest;

/** Move a pending follow-up to a new date/time. */
class RescheduleFollowupRequest extends FormRequest
{
    use ParsesNextFollowup;

    public function rules(): array
    {
        return [
            ...$this->nextFollowupRules(),
            'next_followup_date' => ['required_without:next_followup_at', 'nullable', 'date_format:Y-m-d'],
            'next_followup_at' => ['required_without:next_followup_date', 'nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
    }
}

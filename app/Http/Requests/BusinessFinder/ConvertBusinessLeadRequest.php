<?php

namespace App\Http\Requests\BusinessFinder;

use App\Enums\LeadPriority;
use App\Enums\LeadStatus;
use App\Http\Requests\Concerns\ParsesNextFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Save a researched business to the CRM, optionally scheduling the first call. */
class ConvertBusinessLeadRequest extends FormRequest
{
    use ParsesNextFollowup;

    public function rules(): array
    {
        return [
            'on_duplicate' => ['nullable', Rule::in(['reject', 'link'])],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'lead_status' => ['nullable', Rule::enum(LeadStatus::class)],
            ...$this->nextFollowupRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
        $validator->after(function ($v) {
            if ($this->nextFollowupAt() && $this->route('businessLead')?->do_not_contact) {
                $v->errors()->add('next_followup_date', 'This business is marked "Do not contact". A call cannot be scheduled.');
            }
        });
    }

    public function options(): array
    {
        return [
            'on_duplicate' => $this->input('on_duplicate', 'reject'),
            'priority' => $this->input('priority'),
            'status' => $this->input('lead_status'),
            'next_at' => $this->nextFollowupAt(),
        ];
    }
}

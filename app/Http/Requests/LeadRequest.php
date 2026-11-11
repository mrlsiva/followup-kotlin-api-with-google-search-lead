<?php

namespace App\Http\Requests;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\Concerns\ParsesNextFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for create (POST) and update (PUT/PATCH — every field optional). */
class LeadRequest extends FormRequest
{
    use ParsesNextFollowup;

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'priority' => ['sometimes', Rule::enum(LeadPriority::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->nextFollowupRules(),
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
    }

    public function messages(): array
    {
        return ['mobile.regex' => 'The mobile number may contain digits, spaces, +, -, ( and ) only.'];
    }

    /** Lead columns only (next follow-up is handled by FollowupService). */
    public function leadData(): array
    {
        return $this->safe()->only(['name', 'mobile', 'email', 'company', 'source', 'status', 'priority', 'notes']);
    }
}

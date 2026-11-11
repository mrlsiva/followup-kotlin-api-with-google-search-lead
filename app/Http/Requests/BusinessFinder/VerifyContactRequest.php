<?php

namespace App\Http\Requests\BusinessFinder;

use App\Enums\ContactStatus;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Manual contact verification by the salesperson. */
class VerifyContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('source_url')) {
            $this->merge(['source_url' => WebsiteChecker::normalize($this->input('source_url')) ?? $this->input('source_url')]);
        }
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::enum(ContactStatus::class)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{6,20}$/'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact_source' => ['nullable', 'string', 'max:100'],
            'source_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'verification_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'The phone number may contain digits, spaces, +, -, ( and ) only.'];
    }
}

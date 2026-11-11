<?php

namespace App\Http\Requests\BusinessFinder;

use App\Enums\BusinessCategory;
use App\Enums\ContactStatus;
use App\Enums\LeadPriority;
use App\Enums\LeadStatus;
use App\Enums\WebsiteStatus;
use App\Http\Requests\Concerns\ParsesNextFollowup;
use App\Services\BusinessFinder\WebsiteChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Add Business to CRM" form (POST) and edit (PUT — every field optional).
 * POST also accepts CRM options: save_to_crm, on_duplicate, priority, lead_status and the first call
 * (next_followup_date + next_followup_time, or next_followup_at).
 */
class BusinessLeadRequest extends FormRequest
{
    use ParsesNextFollowup;

    public const FIELDS = [
        'business_name', 'category', 'contact_person', 'phone', 'email', 'address', 'area', 'city',
        'district', 'pincode', 'source_url', 'website_url', 'website_status', 'contact_status',
        'contact_source', 'contact_verified_at', 'notes', 'business_search_id',
    ];

    protected function prepareForValidation(): void
    {
        foreach (['website_url', 'source_url'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => WebsiteChecker::normalize($this->input($field)) ?? $this->input($field)]);
            }
        }
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $userId = $this->user()->id;

        return [
            'business_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'category' => ['sometimes', Rule::in(BusinessCategory::assignable())],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:60'],
            'pincode' => ['nullable', 'regex:/^\d{6}$/'],
            'source_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'website_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'website_status' => ['sometimes', Rule::enum(WebsiteStatus::class)],
            'contact_status' => ['sometimes', Rule::enum(ContactStatus::class)],
            'contact_source' => ['nullable', 'string', 'max:100'],
            'contact_verified_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'business_search_id' => ['nullable', 'integer', Rule::exists('business_searches', 'id')->where('user_id', $userId)],

            // CRM options (create only)
            'save_to_crm' => ['nullable', 'boolean'],
            'on_duplicate' => ['nullable', Rule::in(['reject', 'link'])],
            'priority' => ['nullable', Rule::enum(LeadPriority::class)],
            'lead_status' => ['nullable', Rule::enum(LeadStatus::class)],
            ...$this->nextFollowupRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number may contain digits, spaces, +, -, ( and ) only.',
            'pincode.regex' => 'The PIN code must be 6 digits.',
            'website_url.url' => 'Enter a valid website address, e.g. https://example.com',
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateNextIsFuture($validator);
        $validator->after(function ($v) {
            $status = $this->input('website_status');
            if ($status && $status !== WebsiteStatus::NoWebsite->value && $status !== WebsiteStatus::Pending->value
                && ! $this->filled('website_url') && ! $this->route('businessLead')?->website_url) {
                $v->errors()->add('website_url', 'Enter the website address for this website status.');
            }
            if ($this->input('contact_status') === ContactStatus::DoNotContact->value && $this->nextFollowupAt()) {
                $v->errors()->add('next_followup_date', 'A "Do not contact" business cannot have a call scheduled.');
            }
        });
    }

    /** Business columns only. */
    public function businessData(): array
    {
        return $this->safe()->only(self::FIELDS);
    }

    /** Options for BusinessLeadService::create(). */
    public function crmOptions(): array
    {
        return [
            'save_to_crm' => $this->has('save_to_crm') ? $this->boolean('save_to_crm') : true,
            'on_duplicate' => $this->input('on_duplicate', 'reject'),
            'priority' => $this->input('priority'),
            'status' => $this->input('lead_status'),
            'next_at' => $this->nextFollowupAt(),
        ];
    }
}

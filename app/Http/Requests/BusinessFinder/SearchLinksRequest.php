<?php

namespace App\Http\Requests\BusinessFinder;

use App\Enums\BusinessCategory;
use App\Enums\WebsiteStatus;
use App\Services\BusinessFinder\BusinessLeadQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchLinksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:150'],
            'match_type' => ['nullable', Rule::in(['exact', 'partial'])],
            'keywords' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::enum(BusinessCategory::class)],
            'district' => ['nullable', Rule::in(array_keys(config('business_finder.districts')))],
            'city' => ['nullable', 'string', 'max:80'],
            'area' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'regex:/^6\d{5}$/'],
            'quantity' => ['nullable', 'integer', Rule::in(config('business_finder.quantities'))],
            'website_filter' => ['nullable', Rule::in(['All', ...WebsiteStatus::values()])],
            'contact_filter' => ['nullable', Rule::in(BusinessLeadQuery::CONTACT_FILTERS)],
            'save_history' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['pincode.regex' => 'Enter a 6-digit Tamil Nadu PIN code (starts with 6).'];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $name = trim((string) $this->input('business_name'));
            $keywords = trim((string) $this->input('keywords'));
            if ($name === '' && $keywords === '' && ! $this->filled('category')) {
                $v->errors()->add('category', 'Choose a category or enter a business name or keywords.');
            }
            if ($this->input('category') === BusinessCategory::Other->value && $name === '' && $keywords === '') {
                $v->errors()->add('keywords', 'For "Other businesses", enter keywords such as "bakery" or "printing press".');
            }
        });
    }

    /** Validated search parameters with defaults. */
    public function params(): array
    {
        $d = $this->validated();

        return [
            'business_name' => isset($d['business_name']) ? trim($d['business_name']) : null,
            'match_type' => $d['match_type'] ?? 'partial',
            'keywords' => isset($d['keywords']) ? trim($d['keywords']) : null,
            'category' => $d['category'] ?? null,
            'district' => $d['district'] ?? null,
            'city' => isset($d['city']) ? trim($d['city']) : null,
            'area' => isset($d['area']) ? trim($d['area']) : null,
            'pincode' => $d['pincode'] ?? null,
            'quantity' => (int) ($d['quantity'] ?? 20),
            'website_filter' => $d['website_filter'] ?? null,
            'contact_filter' => $d['contact_filter'] ?? null,
        ];
    }
}

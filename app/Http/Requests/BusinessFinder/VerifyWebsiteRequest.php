<?php

namespace App\Http\Requests\BusinessFinder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /business-finder/verify-website: `url` required.
 * POST /business-finder/leads/{id}/verify-website: `url` optional (defaults to the saved website).
 */
class VerifyWebsiteRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->route('businessLead') ? 'nullable' : 'required';

        return [
            'url' => [$required, 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->filled('url') && $this->route('businessLead') && ! $this->route('businessLead')->website_url) {
                $v->errors()->add('url', 'This business has no website address. Enter one to check.');
            }
        });
    }
}

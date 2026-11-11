<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLeadsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:csv,txt,xlsx'],
        ];
    }
}

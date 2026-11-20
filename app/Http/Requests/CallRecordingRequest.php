<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Upload or replace a call recording (POST /followups/{followup}/recording, multipart field `recording`). */
class CallRecordingRequest extends FormRequest
{
    public function rules(): array
    {
        return ['recording' => ['required', ...self::fileRules()]];
    }

    /**
     * Rules for a call recording file: an allowed extension (m4a, mp3, amr …), audio content and a size limit.
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'file',
            'extensions:'.implode(',', array_keys(config('crm.recordings.formats'))),
            'mimetypes:'.implode(',', config('crm.recordings.detected_mimes')),
            'max:'.config('crm.recordings.max_kb'),
        ];
    }

    public function messages(): array
    {
        return [
            'recording.extensions' => 'The recording must be one of: '.implode(', ', array_keys(config('crm.recordings.formats'))).'.',
            'recording.mimetypes' => 'The recording does not look like an audio file.',
        ];
    }
}

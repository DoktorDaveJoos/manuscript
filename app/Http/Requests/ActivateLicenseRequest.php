<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'regex:/^MANU\.[A-F0-9]{8}\.[A-Za-z0-9_-]{86}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'license_key.regex' => 'The license key format is invalid.',
        ];
    }
}

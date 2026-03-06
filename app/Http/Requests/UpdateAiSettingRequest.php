<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'text_model' => ['nullable', 'string', 'max:100'],
            'embedding_model' => ['nullable', 'string', 'max:100'],
            'embedding_dimensions' => ['nullable', 'integer', 'min:1', 'max:8192'],
            'api_version' => ['nullable', 'string', 'max:20'],
            'enabled' => ['required', 'boolean'],
        ];
    }
}

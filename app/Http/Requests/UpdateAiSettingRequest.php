<?php

namespace App\Http\Requests;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $provider = $this->route('provider');
        $provider = $provider instanceof AiProvider ? $provider : AiProvider::from($provider);

        return [
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => [
                Rule::excludeIf(! $provider->requiresBaseUrl()),
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'text_model' => [
                Rule::excludeIf(! $provider->requiresTextModel()),
                'nullable',
                'string',
                'max:255',
            ],
            'enabled' => ['required', 'boolean'],
        ];
    }
}

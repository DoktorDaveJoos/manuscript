<?php

namespace App\Http\Requests;

use App\Enums\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookAiModelRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ai_provider' => ['sometimes', Rule::enum(AiProvider::class)],
            'ai_model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ai_enabled' => ['sometimes', 'boolean'],
        ];
    }
}

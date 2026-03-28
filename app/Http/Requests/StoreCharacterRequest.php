<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'storylines' => ['nullable', 'array'],
            'storylines.*' => ['integer'],
            'role' => ['nullable', 'string', 'in:protagonist,supporting,mentioned'],
            'first_appearance' => ['nullable', 'integer', 'exists:chapters,id'],
            'chapter_ids' => ['nullable', 'array'],
            'chapter_ids.*' => ['integer', 'exists:chapters,id'],
        ];
    }
}

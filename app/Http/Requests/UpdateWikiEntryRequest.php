<?php

namespace App\Http\Requests;

use App\Enums\WikiEntryKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWikiEntryRequest extends FormRequest
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
            'kind' => ['sometimes', Rule::enum(WikiEntryKind::class)],
            'type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'first_appearance' => ['nullable', 'integer', 'exists:chapters,id'],
            'chapter_ids' => ['nullable', 'array'],
            'chapter_ids.*' => ['integer', 'exists:chapters,id'],
        ];
    }
}

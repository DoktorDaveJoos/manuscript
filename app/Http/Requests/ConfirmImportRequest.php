<?php

namespace App\Http\Requests;

use App\Enums\StorylineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConfirmImportRequest extends FormRequest
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
            'storylines' => ['required', 'array', 'min:1'],
            'storylines.*.name' => ['required', 'string', 'max:255'],
            'storylines.*.type' => ['required', new Enum(StorylineType::class)],
            'storylines.*.chapters' => ['required', 'array', 'min:1'],
            'storylines.*.chapters.*.title' => ['required', 'string', 'max:255'],
            'storylines.*.chapters.*.content' => ['present', 'nullable', 'string'],
            'storylines.*.chapters.*.word_count' => ['required', 'integer', 'min:0'],
            'storylines.*.chapters.*.included' => ['required', 'boolean'],
        ];
    }
}

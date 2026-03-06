<?php

namespace App\Http\Requests;

use App\Enums\StorylineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ParseImportRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1'],
            'files.*.file' => ['required', 'file', 'mimes:docx', 'max:10240'],
            'files.*.storyline_name' => ['required', 'string', 'max:255'],
            'files.*.storyline_type' => ['required', new Enum(StorylineType::class)],
        ];
    }
}

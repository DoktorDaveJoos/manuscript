<?php

namespace App\Http\Requests;

use App\Enums\StorylineType;
use App\Services\Parsers\DocumentParserFactory;
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
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*.file' => ['required', 'file', 'extensions:'.implode(',', DocumentParserFactory::SUPPORTED_EXTENSIONS), 'max:10240'],
            'files.*.storyline_name' => ['required', 'string', 'max:255'],
            'files.*.storyline_type' => ['required', new Enum(StorylineType::class)],
            'merge_into_single_storyline' => ['sometimes', 'boolean'],
        ];
    }
}

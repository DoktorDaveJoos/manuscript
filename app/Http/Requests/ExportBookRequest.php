<?php

namespace App\Http\Requests;

use App\Enums\BackMatterType;
use App\Enums\ExportFormat;
use App\Enums\FrontMatterType;
use App\Enums\TrimSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportBookRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'scope' => ['required_without:chapter_ids', 'in:full,chapter,storyline'],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'storyline_id' => ['nullable', 'integer', 'exists:storylines,id'],
            'chapter_ids' => ['nullable', 'array'],
            'chapter_ids.*' => ['integer', 'exists:chapters,id'],
            'include_chapter_titles' => ['boolean'],
            'include_act_breaks' => ['boolean'],
            'include_table_of_contents' => ['boolean'],
            'show_page_numbers' => ['boolean'],
            'trim_size' => ['nullable', Rule::enum(TrimSize::class)],
            'font_size' => ['nullable', 'integer', 'in:10,11,12,13,14'],
            'front_matter' => ['nullable', 'array'],
            'front_matter.*' => ['string', Rule::enum(FrontMatterType::class)],
            'back_matter' => ['nullable', 'array'],
            'back_matter.*' => ['string', Rule::enum(BackMatterType::class)],
            'template' => ['nullable', 'string', 'in:classic'],
        ];
    }
}

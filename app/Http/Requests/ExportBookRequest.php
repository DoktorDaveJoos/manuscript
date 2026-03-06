<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportBookRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'in:docx,txt'],
            'scope' => ['required', 'in:full,chapter,storyline'],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'storyline_id' => ['nullable', 'integer', 'exists:storylines,id'],
            'include_chapter_titles' => ['boolean'],
            'include_act_breaks' => ['boolean'],
        ];
    }
}

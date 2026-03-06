<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'initial_content' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublishSettingsRequest extends FormRequest
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
            'copyright_text' => ['nullable', 'string', 'max:2000'],
            'dedication_text' => ['nullable', 'string', 'max:2000'],
            'epigraph_text' => ['nullable', 'string', 'max:2000'],
            'epigraph_attribution' => ['nullable', 'string', 'max:255'],
            'acknowledgment_text' => ['nullable', 'string', 'max:5000'],
            'about_author_text' => ['nullable', 'string', 'max:5000'],
            'also_by_text' => ['nullable', 'string', 'max:5000'],
            'publisher_name' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSceneContentRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:5000000'],
            'expected_current_version_id' => ['nullable', 'integer'],
            'expected_content_version' => ['present', 'integer', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContinueWritingRefineRequest extends FormRequest
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
            'content' => ['required', 'string'],
        ];
    }
}

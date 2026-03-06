<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderChaptersRequest extends FormRequest
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
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:chapters,id'],
            'order.*.storyline_id' => ['required', 'integer', 'exists:storylines,id'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\AnalysisType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunAnalysisRequest extends FormRequest
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
            'type' => ['required', Rule::enum(AnalysisType::class)],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
        ];
    }
}

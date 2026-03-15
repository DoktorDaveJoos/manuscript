<?php

namespace App\Http\Requests;

use App\Enums\PlotPointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetupPlotStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string', Rule::in(['three_act', 'five_act', 'heros_journey'])],
            'acts' => ['required', 'array', 'min:1'],
            'acts.*.title' => ['required', 'string', 'max:255'],
            'acts.*.color' => ['nullable', 'string', 'max:7'],
            'acts.*.beats' => ['required', 'array', 'min:1'],
            'acts.*.beats.*.title' => ['required', 'string', 'max:255'],
            'acts.*.beats.*.type' => ['required', Rule::enum(PlotPointType::class)],
            'chapter_assignments' => ['nullable', 'array'],
            'chapter_assignments.*' => ['array'],
            'chapter_assignments.*.*' => ['integer', Rule::exists('chapters', 'id')->where('book_id', $this->route('book')->id)],
        ];
    }
}

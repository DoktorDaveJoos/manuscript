<?php

namespace App\Http\Requests;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlotPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::enum(PlotPointType::class)],
            'status' => ['sometimes', Rule::enum(PlotPointStatus::class)],
            'storyline_id' => ['nullable', 'exists:storylines,id'],
            'act_id' => ['nullable', 'exists:acts,id'],
            'intended_chapter_id' => ['nullable', 'exists:chapters,id'],
            'characters' => ['sometimes', 'array'],
            'characters.*.id' => ['required', 'integer', Rule::exists('characters', 'id')->where('book_id', $this->route('book')->id)],
            'characters.*.role' => ['required', 'string', Rule::in(['key', 'supporting', 'mentioned'])],
        ];
    }
}

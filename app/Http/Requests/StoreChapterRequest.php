<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChapterRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'storyline_id' => ['required', 'integer', 'exists:storylines,id'],
            'beat_id' => ['sometimes', 'nullable', 'integer', Rule::exists('beats', 'id')->where(function ($query) {
                $query->whereIn('plot_point_id', $this->route('book')->plotPoints()->select('id'));
            })],
        ];
    }
}

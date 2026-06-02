<?php

namespace App\Http\Requests;

use App\Enums\TrimSize;
use App\Services\Export\CoverService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateCoverRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:200'],
            'trim_size' => ['nullable', 'string', Rule::in(array_map(fn (TrimSize $t) => $t->value, TrimSize::cases()))],
            'spine_width' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'face' => ['nullable', 'string', Rule::in([CoverService::FACE_FRONT, CoverService::FACE_BACK, CoverService::FACE_WRAPAROUND])],
        ];
    }
}

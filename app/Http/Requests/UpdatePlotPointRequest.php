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
            'act_id' => ['nullable', 'exists:acts,id'],
        ];
    }
}

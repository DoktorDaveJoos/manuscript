<?php

namespace App\Http\Requests;

use App\Enums\ConnectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlotPointConnectionRequest extends FormRequest
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
            'source_plot_point_id' => ['required', 'exists:plot_points,id'],
            'target_plot_point_id' => ['required', 'exists:plot_points,id', 'different:source_plot_point_id'],
            'type' => ['required', Rule::enum(ConnectionType::class)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignChapterActRequest extends FormRequest
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
            'act_id' => [
                'nullable',
                'integer',
                Rule::exists('acts', 'id')->where('book_id', $this->route('book')->id),
            ],
        ];
    }
}

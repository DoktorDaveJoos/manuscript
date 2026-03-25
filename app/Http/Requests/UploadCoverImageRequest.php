<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCoverImageRequest extends FormRequest
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
            'cover_image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ];
    }
}

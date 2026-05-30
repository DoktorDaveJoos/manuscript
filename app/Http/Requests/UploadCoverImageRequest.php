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
            // Present only when the image was produced by the built-in cover generator,
            // so the generator dialog can reopen with the same values.
            'cover_settings' => ['nullable', 'array'],
            'cover_settings.title' => ['nullable', 'string', 'max:200'],
            'cover_settings.subtitle' => ['nullable', 'string', 'max:200'],
            'cover_settings.author' => ['nullable', 'string', 'max:200'],
            'cover_settings.trim_size' => ['nullable', 'string', 'max:50'],
            'cover_settings.spine_width' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ];
    }
}

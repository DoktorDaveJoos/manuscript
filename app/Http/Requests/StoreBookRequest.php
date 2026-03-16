<?php

namespace App\Http\Requests;

use App\Enums\Genre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
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
            'author' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:5'],
            'genre' => ['nullable', Rule::enum(Genre::class)],
            'secondary_genres' => ['nullable', 'array'],
            'secondary_genres.*' => [Rule::enum(Genre::class)],
        ];
    }
}

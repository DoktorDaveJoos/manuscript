<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookMatterChapterRequest extends FormRequest
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
        /** @var Book $book */
        $book = $this->route('book');

        return [
            'chapter_id' => [
                'nullable',
                'integer',
                Rule::exists('chapters', 'id')->where(fn ($query) => $query
                    ->where('book_id', $book->id)
                    ->whereNull('deleted_at')),
            ],
        ];
    }
}

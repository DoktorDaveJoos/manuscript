<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderStorylinesRequest extends FormRequest
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
        /** @var Book $book */
        $book = $this->route('book');

        return [
            'order' => ['required', 'array', 'list'],
            'order.*' => [
                'integer',
                'distinct',
                Rule::exists('storylines', 'id')->where(fn ($query) => $query
                    ->where('book_id', $book->id)
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Book $book */
            $book = $this->route('book');
            $submittedIds = collect($this->input('order', []))
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();
            $expectedIds = $book->storylines()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($submittedIds->all() !== $expectedIds->all()) {
                $validator->errors()->add('order', __('The order must contain every storyline exactly once.'));
            }
        }];
    }
}

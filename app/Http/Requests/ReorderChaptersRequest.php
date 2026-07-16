<?php

namespace App\Http\Requests;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderChaptersRequest extends FormRequest
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
            'order.*' => ['required', 'array:id,storyline_id'],
            'order.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('chapters', 'id')->where(fn ($query) => $query
                    ->where('book_id', $book->id)
                    ->whereNull('deleted_at')),
            ],
            'order.*.storyline_id' => [
                'required',
                'integer',
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
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();
            $expectedIds = $book->chapters()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($submittedIds->all() !== $expectedIds->all()) {
                $validator->errors()->add('order', __('The order must contain every chapter exactly once.'));
            }
        }];
    }
}

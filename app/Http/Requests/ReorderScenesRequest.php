<?php

namespace App\Http\Requests;

use App\Models\Chapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderScenesRequest extends FormRequest
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
        /** @var Chapter $chapter */
        $chapter = $this->route('chapter');

        return [
            'order' => ['required', 'array', 'list'],
            'order.*' => [
                'integer',
                'distinct',
                Rule::exists('scenes', 'id')->where(fn ($query) => $query
                    ->where('chapter_id', $chapter->id)
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

            /** @var Chapter $chapter */
            $chapter = $this->route('chapter');
            $submittedIds = collect($this->input('order', []))
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();
            $expectedIds = $chapter->scenes()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($submittedIds->all() !== $expectedIds->all()) {
                $validator->errors()->add('order', __('The order must contain every scene exactly once.'));
            }
        }];
    }
}

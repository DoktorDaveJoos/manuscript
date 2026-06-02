<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContinueWritingRequest extends FormRequest
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
            'hint' => ['nullable', 'string', 'max:1000'],
            'word_goal' => ['nullable', 'integer', 'min:30', 'max:500'],
            'before' => ['nullable', 'string'],
            'after' => ['nullable', 'string'],
            'expected_current_version_id' => ['nullable', 'integer'],
        ];
    }

    public function expectedCurrentVersionId(): ?int
    {
        $value = $this->validated('expected_current_version_id');

        return $value === null ? null : (int) $value;
    }

    public function wordGoal(): int
    {
        return (int) ($this->validated('word_goal') ?? 120);
    }

    public function hint(): ?string
    {
        $hint = $this->validated('hint');

        return $hint === null ? null : trim($hint);
    }

    public function beforeProse(): ?string
    {
        return $this->validated('before');
    }

    public function afterProse(): ?string
    {
        return $this->validated('after');
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RewriteSelectionRequest extends FormRequest
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
            'selection' => ['required', 'string', 'max:8000'],
            'hint' => ['nullable', 'string', 'max:1000'],
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

    public function selection(): string
    {
        return trim((string) $this->validated('selection'));
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

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SceneStructureApplyRequest extends FormRequest
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
            'content_hash' => ['required', 'string'],
            'scenes' => ['required', 'array', 'min:1'],
            'scenes.*.title' => ['required', 'string', 'max:255'],
            'scenes.*.start_paragraph' => ['required', 'integer', 'min:0'],
        ];
    }

    public function contentHash(): string
    {
        return (string) $this->validated('content_hash');
    }

    /**
     * @return array<int, array{title: string, start_paragraph: int}>
     */
    public function scenes(): array
    {
        return collect($this->validated('scenes'))
            ->map(fn (array $scene) => [
                'title' => (string) $scene['title'],
                'start_paragraph' => (int) $scene['start_paragraph'],
            ])
            ->values()
            ->all();
    }
}

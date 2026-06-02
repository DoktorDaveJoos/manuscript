<?php

namespace App\Http\Requests;

use App\Enums\PreparationStep;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAiPreparationRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'steps' => ['sometimes', 'array', 'min:1'],
            'steps.*' => ['string', Rule::in(PreparationStep::values())],
        ];
    }

    /**
     * Enforce step prerequisites (e.g. story bible & health need chapter analysis).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $selected = $this->input('steps');

            if (! is_array($selected) || empty($selected)) {
                return;
            }

            foreach ($selected as $value) {
                $step = PreparationStep::tryFrom($value);

                if (! $step) {
                    continue;
                }

                foreach ($step->requires() as $prerequisite) {
                    if (! in_array($prerequisite->value, $selected, true)) {
                        $validator->errors()->add(
                            'steps',
                            __(':step requires :prerequisite to also be selected.', [
                                'step' => $step->value,
                                'prerequisite' => $prerequisite->value,
                            ]),
                        );
                    }
                }
            }
        });
    }

    /**
     * The resolved list of selected step values, defaulting to all steps.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        $selected = $this->validated('steps');

        if (! is_array($selected) || empty($selected)) {
            return PreparationStep::values();
        }

        // Preserve canonical pipeline order regardless of payload ordering.
        return array_values(array_filter(
            PreparationStep::values(),
            fn (string $value) => in_array($value, $selected, true),
        ));
    }
}

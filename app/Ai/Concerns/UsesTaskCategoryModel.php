<?php

namespace App\Ai\Concerns;

use App\Enums\AiTaskCategory;
use App\Models\AiSetting;

trait UsesTaskCategoryModel
{
    abstract public static function taskCategory(): AiTaskCategory;

    public function model(): ?string
    {
        $provider = AiSetting::activeProvider();

        return $provider?->modelForCategory(static::taskCategory())
            ?? $provider?->text_model;
    }
}

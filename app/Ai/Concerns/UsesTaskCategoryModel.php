<?php

namespace App\Ai\Concerns;

use App\Enums\AiTaskCategory;
use App\Models\AiSetting;

trait UsesTaskCategoryModel
{
    abstract public static function taskCategory(): AiTaskCategory;

    public function model(): ?string
    {
        return AiSetting::activeProvider()?->modelForCategory(static::taskCategory());
    }
}

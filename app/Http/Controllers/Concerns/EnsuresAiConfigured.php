<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiSetting;

trait EnsuresAiConfigured
{
    protected function ensureAiConfigured(): void
    {
        set_time_limit(300);

        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            __('No AI provider configured.'),
        );

        $setting->injectConfig();
    }
}

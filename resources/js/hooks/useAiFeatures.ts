import { usePage } from '@inertiajs/react';
import type { AppSettings } from '@/types/models';

type SharedProps = {
    app_settings: AppSettings;
    ai_configured: boolean;
    ai_provider_label: string | null;
    ai_default_model: string | null;
};

export function useAiFeatures() {
    const { app_settings, ai_configured, ai_provider_label, ai_default_model } =
        usePage<SharedProps>().props;

    return {
        visible: app_settings.show_ai_features,
        usable: ai_configured && app_settings.show_ai_features,
        configured: ai_configured,
        providerLabel: ai_provider_label ?? null,
        defaultModel: ai_default_model ?? null,
    };
}

import { usePage } from '@inertiajs/react';
import type { AppSettings, License } from '@/types/models';

type SharedProps = {
    app_settings: AppSettings;
    license: License;
    ai_configured: boolean;
    ai_provider_label: string | null;
};

export function useAiFeatures() {
    const { app_settings, license, ai_configured, ai_provider_label } =
        usePage<SharedProps>().props;

    return {
        visible: app_settings.show_ai_features,
        usable:
            license.active && ai_configured && app_settings.show_ai_features,
        licensed: license.active,
        configured: ai_configured,
        providerLabel: ai_provider_label ?? null,
    };
}

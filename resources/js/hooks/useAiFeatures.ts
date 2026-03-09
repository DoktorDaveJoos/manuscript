import type { AppSettings, License } from '@/types/models';
import { usePage } from '@inertiajs/react';

type SharedProps = {
    app_settings: AppSettings;
    license: License;
    ai_configured: boolean;
};

export function useAiFeatures() {
    const { app_settings, license, ai_configured } = usePage<SharedProps>().props;

    return {
        visible: app_settings.show_ai_features,
        usable: license.active && ai_configured && app_settings.show_ai_features,
        licensed: license.active,
        configured: ai_configured,
    };
}

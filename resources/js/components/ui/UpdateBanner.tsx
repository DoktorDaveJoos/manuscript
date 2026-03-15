import { useAutoUpdater } from '@/hooks/useAutoUpdater';
import { X } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

const DISMISSED_KEY = 'update-banner-dismissed';

function isDismissed(): boolean {
    try {
        return sessionStorage.getItem(DISMISSED_KEY) === 'true';
    } catch {
        return false;
    }
}

export default function UpdateBanner() {
    const { t } = useTranslation('settings');
    const { state, installUpdate } = useAutoUpdater();
    const [dismissed, setDismissed] = useState(isDismissed);

    const dismiss = useCallback(() => {
        setDismissed(true);
        try {
            sessionStorage.setItem(DISMISSED_KEY, 'true');
        } catch {
            // sessionStorage unavailable
        }
    }, []);

    if (state.status !== 'ready' || dismissed) {
        return null;
    }

    return (
        <div className="flex items-center justify-between bg-accent px-4 py-2 text-[13px] text-white">
            <span>
                {t('appearance.update.bannerMessage', { version: state.version })}
            </span>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={installUpdate}
                    className="rounded-md bg-white/20 px-3 py-1 font-medium text-white transition-colors hover:bg-white/30"
                >
                    {t('appearance.update.restart')}
                </button>
                <button
                    type="button"
                    onClick={dismiss}
                    className="rounded-md px-1.5 py-1 text-white/70 transition-colors hover:text-white"
                    aria-label="Dismiss"
                >
                    <X size={14} />
                </button>
            </div>
        </div>
    );
}

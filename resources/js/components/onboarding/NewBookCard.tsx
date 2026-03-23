import { Lock, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function NewBookCard({
    onClick,
    locked,
}: {
    onClick: () => void;
    locked?: boolean;
}) {
    const { t } = useTranslation('onboarding');

    if (locked) {
        return (
            <div className="flex min-h-[180px] w-[400px] shrink-0 flex-col items-center justify-center gap-3 rounded-[10px] border-2 border-dashed border-border-dashed p-8 opacity-60">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                    <Lock size={16} className="text-ink-faint" />
                </div>
                <span className="text-sm leading-[18px] text-ink-faint">
                    {t('newBookCard.create')}
                </span>
                <a
                    href="https://getmanuscript.app"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-[11px] text-ink-faint underline decoration-ink-faint/40 transition-colors hover:text-ink-muted"
                >
                    Upgrade to Pro
                </a>
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className="flex min-h-[180px] w-[400px] shrink-0 cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border-dashed p-8 transition-colors hover:border-ink-faint"
        >
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                <Plus size={20} className="text-ink-muted" />
            </div>
            <span className="text-sm leading-[18px] text-ink-muted">
                {t('newBookCard.create')}
            </span>
        </button>
    );
}

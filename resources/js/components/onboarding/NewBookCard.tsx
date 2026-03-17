import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function NewBookCard({ onClick }: { onClick: () => void }) {
    const { t } = useTranslation('onboarding');
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex min-h-[180px] w-[400px] shrink-0 cursor-pointer flex-col items-center justify-center gap-3 rounded-[10px] border-2 border-dashed border-border-dashed p-8 transition-colors hover:border-ink-faint"
        >
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-bg">
                <Plus size={18} className="text-ink-muted" />
            </div>
            <span className="text-sm leading-[18px] text-ink-muted">
                {t('newBookCard.create')}
            </span>
        </button>
    );
}

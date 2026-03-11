import { ListBullets } from '@phosphor-icons/react';
import { useTranslation } from 'react-i18next';

export default function WikiEmptyState() {
    const { t } = useTranslation('wiki');

    return (
        <div className="flex flex-1 flex-col items-center justify-center text-center">
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-bg">
                <ListBullets size={20} weight="regular" className="text-ink-faint" />
            </div>
            <p className="text-[13px] text-ink-muted">{t('selectItem')}</p>
        </div>
    );
}

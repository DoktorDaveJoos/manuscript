import { PanelLeft, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Kbd from '@/components/ui/Kbd';

export default function PaneEmptyState({
    onCreateChapter,
}: {
    onCreateChapter: () => void;
}) {
    const { t } = useTranslation('editor');

    return (
        <div className="flex flex-1 flex-col items-center justify-center gap-6 pb-20">
            <div className="flex flex-col items-center gap-3 text-center">
                <div className="flex size-12 items-center justify-center rounded-xl bg-neutral-bg">
                    <PanelLeft size={24} className="text-ink-muted" />
                </div>
                <h2 className="font-serif text-xl font-semibold text-ink">
                    {t('emptyPane.title', 'No chapters open')}
                </h2>
                <p className="max-w-[320px] text-sm leading-relaxed text-ink-muted">
                    {t(
                        'emptyPane.description',
                        'Select a chapter from the sidebar to start writing, or create a new one.',
                    )}
                </p>
            </div>
            <div className="flex items-center gap-3">
                <Button variant="primary" size="lg" onClick={onCreateChapter}>
                    <Plus size={14} />
                    {t('emptyPane.createChapter', 'New Chapter')}
                </Button>
            </div>
            <div className="mt-4 flex flex-col items-center gap-2 text-[12px] text-ink-faint">
                <span>
                    {t(
                        'emptyPane.tipSplit',
                        'Right-click a chapter to open in a new pane',
                    )}
                </span>
                <span className="flex items-center gap-1.5">
                    <Kbd keys="⌘" />
                    {t('emptyPane.tipModClick', '+ click to open side by side')}
                </span>
            </div>
        </div>
    );
}

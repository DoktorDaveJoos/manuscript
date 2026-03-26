import { Link } from '@inertiajs/react';
import { FileSearch, NotebookText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import PanelHeader from '@/components/ui/PanelHeader';

export default function EditorialReviewPanel({
    chapterNote,
    editorialReviewUrl,
    onClose,
}: {
    chapterNote: string | null;
    editorialReviewUrl: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('editorial-review');

    return (
        <div className="flex h-full shrink-0 flex-col border-l border-border bg-surface-sidebar">
            <PanelHeader
                title={t('panel.title')}
                icon={<NotebookText size={14} className="text-ink-muted" />}
                onClose={onClose}
            />

            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto px-3 py-2">
                {chapterNote === null ? (
                    <div className="flex flex-col items-center gap-3 py-8 text-center">
                        <FileSearch size={24} className="text-ink-faint" />
                        <p className="text-[13px] text-ink-muted">
                            {t('panel.noReview')}
                        </p>
                        <Link
                            href={editorialReviewUrl}
                            className="text-[13px] font-medium text-accent hover:underline"
                        >
                            {t('panel.viewReview')}
                        </Link>
                    </div>
                ) : (
                    <div className="py-2">
                        <p className="text-[13px] leading-relaxed whitespace-pre-line text-ink-muted">
                            {chapterNote}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

import { ArrowUpFromLine, Filter, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { createChapter } from '@/lib/utils';
import type { AiPreparationStatus, Storyline } from '@/types/models';
import AiPreparationProgress from './AiPreparationProgress';

export default function ActionsRow({
    bookId,
    aiEnabled,
    aiPreparation,
    onNormalize,
    storylines,
    licensed = true,
}: {
    bookId: number;
    aiEnabled: boolean;
    aiPreparation: AiPreparationStatus | null;
    onNormalize: () => void;
    storylines: Storyline[];
    licensed?: boolean;
}) {
    const { t } = useTranslation('dashboard');

    const handleAddChapter = () => {
        const firstStorylineId = storylines[0]?.id;
        if (!firstStorylineId) return;
        createChapter(bookId, firstStorylineId, storylines);
    };

    return (
        <div className="flex items-center justify-between">
            <AiPreparationProgress bookId={bookId} aiEnabled={aiEnabled} initialStatus={aiPreparation} licensed={licensed} />

            <div className="flex items-center divide-x divide-border rounded-lg border border-border bg-surface-card">
                <button
                    type="button"
                    onClick={onNormalize}
                    className="flex items-center gap-2 px-5 py-2.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg/50"
                >
                    <Filter size={14} className="shrink-0" />
                    {t('actions.normalize')}
                </button>

                <button
                    type="button"
                    disabled
                    className="flex items-center gap-2 px-5 py-2.5 text-[13px] font-medium text-ink-faint"
                >
                    <ArrowUpFromLine size={14} className="shrink-0" />
                    {t('actions.export')}
                </button>

                <button
                    type="button"
                    onClick={handleAddChapter}
                    className="flex items-center gap-2 px-5 py-2.5 text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg/50"
                >
                    <Plus size={14} className="shrink-0" />
                    {t('actions.addChapter')}
                </button>
            </div>
        </div>
    );
}

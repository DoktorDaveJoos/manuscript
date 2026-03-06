import { createChapter } from '@/lib/utils';
import type { AiPreparationStatus, Storyline } from '@/types/models';
import { Export, FunnelSimple, Plus } from '@phosphor-icons/react';
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
                    className="flex items-center gap-2 px-[18px] py-[11px] text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg/50"
                >
                    <FunnelSimple size={14} className="shrink-0" />
                    Normalize
                </button>

                <button
                    type="button"
                    disabled
                    className="flex items-center gap-2 px-[18px] py-[11px] text-[13px] font-medium text-ink-faint"
                >
                    <Export size={14} className="shrink-0" />
                    Export
                </button>

                <button
                    type="button"
                    onClick={handleAddChapter}
                    className="flex items-center gap-2 px-[18px] py-[11px] text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg/50"
                >
                    <Plus size={14} className="shrink-0" />
                    Add chapter
                </button>
            </div>
        </div>
    );
}

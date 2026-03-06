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
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                        <path
                            d="M2 4h12M4 8h8M6 12h4"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                        />
                    </svg>
                    Normalize
                </button>

                <button
                    type="button"
                    disabled
                    className="flex items-center gap-2 px-[18px] py-[11px] text-[13px] font-medium text-ink-faint"
                >
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                        <path
                            d="M8 2v8m0 0l-3-3m3 3l3-3M3 13h10"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                    Export
                </button>

                <button
                    type="button"
                    onClick={handleAddChapter}
                    className="flex items-center gap-2 px-[18px] py-[11px] text-[13px] font-medium text-ink transition-colors hover:bg-neutral-bg/50"
                >
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                        <path d="M8 3v10M3 8h10" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                    </svg>
                    Add chapter
                </button>
            </div>
        </div>
    );
}

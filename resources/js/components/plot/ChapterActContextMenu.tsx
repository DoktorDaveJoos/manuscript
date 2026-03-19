import { ArrowRight, Download, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import ContextMenu from '@/components/ui/ContextMenu';
import type { Act } from '@/types/models';

const ACT_COLORS: Record<number, string> = {
    0: 'var(--color-accent)',
    1: 'var(--color-status-revised)',
    2: '#A3C4A0',
};

export default function ChapterActContextMenu({
    acts,
    currentActId,
    chapterId,
    position,
    onAssign,
    onExport,
    onClose,
}: {
    acts: Act[];
    currentActId: number | null;
    chapterId?: number;
    position: { x: number; y: number };
    onAssign: (actId: number | null) => void;
    onExport?: (chapterId: number) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('plot');

    return (
        <ContextMenu position={position} onClose={onClose}>
            <ContextMenu.Submenu
                icon={
                    <ArrowRight size={14} className="shrink-0 text-ink-muted" />
                }
                label={t('contextMenu.assignTo')}
            >
                {acts.map((act, i) => (
                    <ContextMenu.Item
                        key={act.id}
                        onClick={() => {
                            onAssign(act.id);
                            onClose();
                        }}
                    >
                        <span
                            className="inline-block size-[7px] rounded-full"
                            style={{
                                backgroundColor:
                                    ACT_COLORS[i] ?? 'var(--color-accent)',
                            }}
                        />
                        {t('actTitle', {
                            number: act.number,
                            title: act.title,
                        })}
                    </ContextMenu.Item>
                ))}
            </ContextMenu.Submenu>

            {currentActId != null && (
                <ContextMenu.Item
                    icon={<X size={14} className="shrink-0 text-ink-muted" />}
                    label={t('contextMenu.unassign')}
                    onClick={() => {
                        onAssign(null);
                        onClose();
                    }}
                />
            )}

            {onExport && chapterId != null && (
                <>
                    <ContextMenu.Separator />
                    <ContextMenu.Item
                        icon={
                            <Download
                                size={14}
                                className="shrink-0 text-ink-muted"
                            />
                        }
                        label={t('contextMenu.exportChapter')}
                        onClick={() => {
                            onExport(chapterId);
                            onClose();
                        }}
                    />
                </>
            )}
        </ContextMenu>
    );
}

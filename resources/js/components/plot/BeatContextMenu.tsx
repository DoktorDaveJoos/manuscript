import { router } from '@inertiajs/react';
import { CircleCheck, CircleX, Circle, FilePlus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { store } from '@/actions/App/Http/Controllers/ChapterController';

import ContextMenu from '@/components/ui/ContextMenu';
import type { Beat, BeatStatus, Storyline } from '@/types/models';

type BeatContextMenuProps = {
    beat: Beat;
    bookId: number;
    storylines: Storyline[];
    position: { x: number; y: number };
    onClose: () => void;
    onStatusChange: (beatId: number, status: BeatStatus) => void;
    onDelete: (beatId: number) => void;
};

export default function BeatContextMenu({
    beat,
    bookId,
    storylines,
    position,
    onClose,
    onStatusChange,
    onDelete,
}: BeatContextMenuProps) {
    const { t } = useTranslation('plot');

    const handleStatusChange = (status: BeatStatus) => {
        onStatusChange(beat.id, status);
        onClose();
    };

    const handleCreateChapter = (storylineId: number) => {
        router.post(
            store.url({ book: bookId }),
            {
                storyline_id: storylineId,
                title: 'New Chapter',
                beat_id: beat.id,
            },
            { preserveScroll: true },
        );
        onClose();
    };

    const handleDelete = () => {
        onDelete(beat.id);
        onClose();
    };

    return (
        <ContextMenu
            position={position}
            onClose={onClose}
            className="w-[220px]"
        >
            <ContextMenu.Submenu
                icon={
                    <FilePlus size={14} className="shrink-0 text-ink-muted" />
                }
                label={t('beat.contextMenu.createChapter')}
            >
                {storylines.map((storyline) => (
                    <ContextMenu.Item
                        key={storyline.id}
                        label={storyline.name}
                        onClick={() => handleCreateChapter(storyline.id)}
                    />
                ))}
            </ContextMenu.Submenu>

            <ContextMenu.Separator />

            <ContextMenu.Item
                icon={
                    <CircleCheck
                        size={14}
                        className="shrink-0 text-status-final"
                    />
                }
                label={t('beat.contextMenu.markFulfilled')}
                onClick={() => handleStatusChange('fulfilled')}
            />
            <ContextMenu.Item
                icon={<Circle size={14} className="shrink-0 text-ink-muted" />}
                label={t('beat.contextMenu.markPlanned')}
                onClick={() => handleStatusChange('planned')}
            />
            <ContextMenu.Item
                icon={<CircleX size={14} className="shrink-0 text-ink-muted" />}
                label={t('beat.contextMenu.markAbandoned')}
                onClick={() => handleStatusChange('abandoned')}
            />

            <ContextMenu.Separator />

            <ContextMenu.Item
                icon={<Trash2 size={14} className="shrink-0" />}
                label={t('beat.contextMenu.delete')}
                variant="danger"
                onClick={handleDelete}
            />
        </ContextMenu>
    );
}

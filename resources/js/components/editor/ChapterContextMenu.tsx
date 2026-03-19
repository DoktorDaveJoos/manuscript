import { router } from '@inertiajs/react';
import { ArrowRight, Circle, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import ContextMenu from '@/components/ui/ContextMenu';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Chapter, ChapterStatus, Storyline } from '@/types/models';
import {
    reorder,
    updateStatus,
} from '@/actions/App/Http/Controllers/ChapterController';

const statusDotClass: Record<ChapterStatus, string> = {
    draft: 'bg-status-draft',
    revised: 'bg-status-revised',
    final: 'bg-status-final',
};

const statusValues: ChapterStatus[] = ['draft', 'revised', 'final'];

export default function ChapterContextMenu({
    bookId,
    chapter,
    storylines,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    bookId: number;
    chapter: Chapter;
    storylines: Storyline[];
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('editor');

    const handleStatusChange = async (status: ChapterStatus) => {
        await fetch(updateStatus.url({ book: bookId, chapter: chapter.id }), {
            method: 'PATCH',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ status }),
        });
        router.reload({ only: ['book'] });
        onClose();
    };

    const handleMove = (storylineId: number) => {
        const allChapters = storylines.flatMap((s) =>
            (s.chapters ?? []).map((ch) => ({
                id: ch.id,
                storyline_id: ch.storyline_id,
            })),
        );
        const order = allChapters.map((ch) => ({
            id: ch.id,
            storyline_id: ch.id === chapter.id ? storylineId : ch.storyline_id,
        }));

        router.post(
            reorder.url(bookId),
            { order },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
            },
        );
    };

    const otherStorylines = storylines.filter(
        (s) => s.id !== chapter.storyline_id,
    );

    return (
        <ContextMenu position={position} onClose={onClose}>
            <ContextMenu.Item
                icon={<Pencil size={14} className="shrink-0 text-ink-muted" />}
                label={t('contextMenu.rename')}
                onClick={() => {
                    onClose();
                    onRename();
                }}
            />

            <ContextMenu.Submenu
                icon={
                    <Circle
                        size={14}
                        fill="currentColor"
                        className="shrink-0 text-ink-muted"
                    />
                }
                label={t('contextMenu.status')}
                width="w-[160px]"
            >
                {statusValues.map((value) => (
                    <ContextMenu.Item
                        key={value}
                        onClick={() => handleStatusChange(value)}
                        className={
                            chapter.status === value ? 'font-medium' : ''
                        }
                    >
                        <span
                            className={`inline-block size-[7px] rounded-full ${statusDotClass[value]}`}
                        />
                        {t(`status.${value}`)}
                    </ContextMenu.Item>
                ))}
            </ContextMenu.Submenu>

            {otherStorylines.length > 0 && (
                <ContextMenu.Submenu
                    icon={
                        <ArrowRight
                            size={14}
                            className="shrink-0 text-ink-muted"
                        />
                    }
                    label={t('contextMenu.moveTo')}
                >
                    {otherStorylines.map((s) => (
                        <ContextMenu.Item
                            key={s.id}
                            onClick={() => handleMove(s.id)}
                        >
                            {s.color && (
                                <span
                                    className="inline-block size-[7px] rounded-full"
                                    style={{
                                        backgroundColor: s.color,
                                    }}
                                />
                            )}
                            {s.name}
                        </ContextMenu.Item>
                    ))}
                </ContextMenu.Submenu>
            )}

            <ContextMenu.Separator />

            <ContextMenu.Item
                icon={<Trash2 size={14} className="shrink-0" />}
                label={t('contextMenu.delete')}
                variant="danger"
                onClick={() => {
                    onClose();
                    onDelete();
                }}
            />
        </ContextMenu>
    );
}

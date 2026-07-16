import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { update } from '@/actions/App/Http/Controllers/StorylineController';
import ContextMenu from '@/components/ui/ContextMenu';
import { ensureSuccessfulResponse, jsonFetchHeaders } from '@/lib/utils';
import type { Storyline } from '@/types/models';
import ColorPicker from './ColorPicker';

export default function StorylineContextMenu({
    bookId,
    storyline,
    isLastStoryline,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    bookId: number;
    storyline: Storyline;
    isLastStoryline: boolean;
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('editor');

    const handleColorChange = async (color: string) => {
        try {
            const response = await fetch(
                update.url({ book: bookId, storyline: storyline.id }),
                {
                    method: 'PATCH',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ name: storyline.name, color }),
                },
            );
            await ensureSuccessfulResponse(response, t('request.failed'));
            router.reload({ only: ['book', 'sidebar_storylines'] });
            onClose();
        } catch (error) {
            toast.error(
                error instanceof Error ? error.message : t('request.failed'),
            );
        }
    };

    return (
        <ContextMenu position={position} onClose={onClose}>
            <ContextMenu.Item
                label={t('contextMenu.rename')}
                onClick={() => {
                    onClose();
                    onRename();
                }}
            />

            <ContextMenu.Submenu
                icon={
                    storyline.color ? (
                        <span
                            className="inline-block size-[7px] rounded-full"
                            style={{ backgroundColor: storyline.color }}
                        />
                    ) : undefined
                }
                label={t('contextMenu.color')}
                width="w-[170px]"
            >
                <ColorPicker
                    value={storyline.color}
                    onChange={handleColorChange}
                />
            </ContextMenu.Submenu>

            <ContextMenu.Separator />

            <ContextMenu.Item
                label={t('contextMenu.deleteStoryline')}
                variant="danger"
                disabled={isLastStoryline}
                onClick={() => {
                    onClose();
                    onDelete();
                }}
            />
        </ContextMenu>
    );
}

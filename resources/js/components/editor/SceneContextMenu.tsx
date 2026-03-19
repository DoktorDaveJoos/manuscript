import { Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import ContextMenu from '@/components/ui/ContextMenu';
import type { Scene } from '@/types/models';

export default function SceneContextMenu({
    canDelete,
    position,
    onClose,
    onRename,
    onDelete,
}: {
    scene: Scene;
    canDelete: boolean;
    position: { x: number; y: number };
    onClose: () => void;
    onRename: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('editor');

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

            {canDelete && (
                <>
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
                </>
            )}
        </ContextMenu>
    );
}

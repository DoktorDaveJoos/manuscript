import { Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import ContextMenu from '@/components/ui/ContextMenu';
import type { Act } from '@/types/models';

type ActContextMenuProps = {
    act: Act;
    position: { x: number; y: number };
    onClose: () => void;
    onDelete: (actId: number) => void;
};

export default function ActContextMenu({
    act,
    position,
    onClose,
    onDelete,
}: ActContextMenuProps) {
    const { t } = useTranslation('plot');

    const handleDelete = () => {
        onDelete(act.id);
        onClose();
    };

    return (
        <ContextMenu
            position={position}
            onClose={onClose}
            className="w-[180px]"
        >
            <ContextMenu.Item
                icon={<Trash2 size={14} className="shrink-0" />}
                label={t('act.contextMenu.delete')}
                variant="danger"
                onClick={handleDelete}
            />
        </ContextMenu>
    );
}

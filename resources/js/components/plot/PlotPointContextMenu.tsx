import { Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import ContextMenu from '@/components/ui/ContextMenu';
import type { PlotPoint } from '@/types/models';

type PlotPointContextMenuProps = {
    plotPoint: PlotPoint;
    position: { x: number; y: number };
    onClose: () => void;
    onDelete: (plotPointId: number) => void;
};

export default function PlotPointContextMenu({
    plotPoint,
    position,
    onClose,
    onDelete,
}: PlotPointContextMenuProps) {
    const { t } = useTranslation('plot');

    const handleDelete = () => {
        onDelete(plotPoint.id);
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
                label={t('plotPoint.contextMenu.delete')}
                variant="danger"
                onClick={handleDelete}
            />
        </ContextMenu>
    );
}

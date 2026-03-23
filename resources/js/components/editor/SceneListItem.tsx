import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import { GripVertical } from 'lucide-react';
import { formatCompactCount } from '@/lib/utils';
import type { Scene } from '@/types/models';

export default function SceneListItem({
    scene,
    onClick,
    dragListeners,
    onContextMenu,
}: {
    scene: Scene;
    onClick: () => void;
    dragListeners?: DraggableSyntheticListeners;
    onContextMenu?: (e: React.MouseEvent) => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            onContextMenu={onContextMenu}
            className="group flex w-full items-center gap-1.5 rounded-md py-1 pr-2.5 pl-[42px] text-left text-[12px] text-ink-faint transition-colors hover:bg-ink/5 hover:text-ink-soft"
        >
            <span
                {...dragListeners}
                className="flex shrink-0 cursor-grab items-center text-ink-faint opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
            >
                <GripVertical size={12} />
            </span>
            <span className="min-w-0 flex-1 truncate">{scene.title}</span>
            <span className="shrink-0 text-[11px]">
                {formatCompactCount(scene.word_count)}
            </span>
        </button>
    );
}

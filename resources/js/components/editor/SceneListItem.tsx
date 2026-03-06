import { formatCompactCount } from '@/lib/utils';
import type { Scene } from '@/types/models';
import { DotsSixVertical } from '@phosphor-icons/react';
import type { DraggableSyntheticListeners } from '@dnd-kit/core';

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
            className="group flex w-full items-center gap-1.5 rounded-[5px] py-1 pl-[42px] pr-2.5 text-left text-[12px] text-ink-faint transition-colors hover:bg-ink/5 hover:text-ink-soft"
        >
            <span
                {...dragListeners}
                className="flex shrink-0 cursor-grab items-center text-ink-faint opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100"
            >
                <DotsSixVertical size={12} />
            </span>
            <span className="min-w-0 flex-1 truncate">{scene.title}</span>
            <span className="shrink-0 text-[11px]">{formatCompactCount(scene.word_count)}</span>
        </button>
    );
}

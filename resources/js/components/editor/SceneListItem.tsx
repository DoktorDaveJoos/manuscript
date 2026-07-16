import type {
    DraggableAttributes,
    DraggableSyntheticListeners,
} from '@dnd-kit/core';
import { GripVertical } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { formatWordCount } from '@/lib/utils';
import type { Scene } from '@/types/models';

export default function SceneListItem({
    scene,
    onClick,
    dragAttributes,
    dragListeners,
    onContextMenu,
    showWordCount = true,
    compactWordCount = true,
}: {
    scene: Scene;
    onClick: () => void;
    dragAttributes?: DraggableAttributes;
    dragListeners?: DraggableSyntheticListeners;
    onContextMenu?: (e: React.MouseEvent) => void;
    showWordCount?: boolean;
    compactWordCount?: boolean;
}) {
    const { t } = useTranslation('editor');

    return (
        <div
            onContextMenu={onContextMenu}
            className="group flex w-full items-center rounded-md pr-2.5 pl-9 text-left text-xs text-ink-faint transition-colors hover:bg-ink/5 hover:text-ink-soft"
        >
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('drag.scene')}
                className="size-6 shrink-0 cursor-grab bg-transparent p-0 text-ink-faint opacity-0 transition-opacity group-hover:opacity-100 hover:bg-transparent focus:opacity-100 active:cursor-grabbing"
                {...dragAttributes}
                {...dragListeners}
            >
                <GripVertical size={12} />
            </Button>
            <Button
                type="button"
                variant="ghost"
                onClick={onClick}
                data-sidebar-scene={scene.id}
                className="h-auto min-w-0 flex-1 justify-start bg-transparent px-1.5 py-1 text-left text-xs text-ink-faint hover:bg-transparent hover:text-ink-soft"
            >
                <span className="min-w-0 flex-1 truncate">{scene.title}</span>
                {showWordCount && (
                    <span className="shrink-0 text-[11px]">
                        {formatWordCount(scene.word_count, compactWordCount)}
                    </span>
                )}
            </Button>
        </div>
    );
}

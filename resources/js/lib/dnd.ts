import { closestCenter } from '@dnd-kit/core';
import type { CollisionDetection } from '@dnd-kit/core';

/**
 * Collision detection that only considers droppable containers matching the
 * active drag item's `data.current.type`. Prevents nested SortableContexts
 * (e.g. storylines vs chapters) from interfering with each other during drag.
 */
export const typedClosestCenter: CollisionDetection = (args) => {
    const activeType = args.active.data.current?.type;
    if (!activeType) return closestCenter(args);

    const filtered = args.droppableContainers.filter(
        (container) => container.data.current?.type === activeType,
    );

    return closestCenter({
        ...args,
        droppableContainers: filtered,
    });
};

import { useCallback, useEffect, useRef, useState } from 'react';

type Options = {
    storageKey: string;
    minWidth: number;
    maxWidth: number;
    defaultWidth: number;
    /** 'left' = dragging right grows (handle on right edge), 'right' = dragging left grows (handle on left edge) */
    direction?: 'left' | 'right';
};

export function useResizablePanel({
    storageKey,
    minWidth,
    maxWidth,
    defaultWidth,
    direction = 'left',
}: Options) {
    const [width, setWidth] = useState(() => {
        const stored = localStorage.getItem(storageKey);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= minWidth && parsed <= maxWidth) return parsed;
        }
        return defaultWidth;
    });

    const widthRef = useRef(width);
    widthRef.current = width;
    const panelRef = useRef<HTMLElement>(null);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const handleMouseDown = useCallback(
        (e: React.MouseEvent) => {
            e.preventDefault();
            const startX = e.clientX;
            const startWidth = widthRef.current;

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            const cleanup = () => {
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                dragCleanupRef.current = null;
            };

            const onMouseMove = (ev: MouseEvent) => {
                const delta =
                    direction === 'right'
                        ? startX - ev.clientX
                        : ev.clientX - startX;
                const newWidth = Math.min(
                    maxWidth,
                    Math.max(minWidth, startWidth + delta),
                );
                widthRef.current = newWidth;
                if (panelRef.current)
                    panelRef.current.style.width = `${newWidth}px`;
            };

            const onMouseUp = () => {
                setWidth(widthRef.current);
                localStorage.setItem(storageKey, String(widthRef.current));
                cleanup();
            };

            dragCleanupRef.current = cleanup;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },
        [direction, maxWidth, minWidth, storageKey],
    );

    return { width, panelRef, handleMouseDown };
}

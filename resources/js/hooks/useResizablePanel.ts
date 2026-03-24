import { useCallback, useEffect, useRef, useState } from 'react';

type Options = {
    storageKey: string;
    minWidth: number;
    maxWidth: number;
    defaultWidth: number;
    /** 'left' = dragging right grows (handle on right edge), 'right' = dragging left grows (handle on left edge) */
    direction?: 'left' | 'right';
    /** Enable collapse behavior with snap-below-threshold */
    collapsible?: boolean;
    /** Width of the collapsed rail (default 48) */
    collapsedWidth?: number;
    /** Snap to collapsed when dragged below this width (default 160) */
    collapseThreshold?: number;
};

export function useResizablePanel({
    storageKey,
    minWidth,
    maxWidth,
    defaultWidth,
    direction = 'left',
    collapsible = false,
    collapsedWidth = 48,
    collapseThreshold = 160,
}: Options) {
    const collapsedKey = `${storageKey}:collapsed`;

    const [expandedWidth, setExpandedWidth] = useState(() => {
        const stored = localStorage.getItem(storageKey);
        if (stored) {
            const parsed = Number(stored);
            if (parsed >= minWidth && parsed <= maxWidth) return parsed;
        }
        return defaultWidth;
    });

    const [isCollapsed, setIsCollapsed] = useState(() => {
        if (!collapsible) return false;
        return localStorage.getItem(collapsedKey) === 'true';
    });

    const expandedWidthRef = useRef(expandedWidth);
    const isCollapsedRef = useRef(isCollapsed);
    useEffect(() => {
        expandedWidthRef.current = expandedWidth;
        isCollapsedRef.current = isCollapsed;
    });

    const panelRef = useRef<HTMLDivElement>(null);
    const dragCleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => {
        return () => dragCleanupRef.current?.();
    }, []);

    const toggleCollapsed = useCallback(() => {
        if (!collapsible) return;
        setIsCollapsed((prev) => {
            const next = !prev;
            localStorage.setItem(collapsedKey, String(next));
            return next;
        });
    }, [collapsible, collapsedKey]);

    const handleMouseDown = useCallback(
        (e: React.MouseEvent) => {
            e.preventDefault();
            const startX = e.clientX;
            const wasCollapsed = isCollapsedRef.current;
            const startWidth = wasCollapsed
                ? collapsedWidth
                : expandedWidthRef.current;
            let dragCollapsed = wasCollapsed;

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            if (panelRef.current) {
                panelRef.current.style.transition = 'none';
            }

            const cleanup = () => {
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                if (panelRef.current) {
                    panelRef.current.style.transition = '';
                }
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                dragCleanupRef.current = null;
            };

            const onMouseMove = (ev: MouseEvent) => {
                const delta =
                    direction === 'right'
                        ? startX - ev.clientX
                        : ev.clientX - startX;
                const rawWidth = startWidth + delta;

                if (collapsible) {
                    if (rawWidth < collapseThreshold) {
                        if (!dragCollapsed && panelRef.current) {
                            const el = panelRef.current;
                            el.style.transition = 'width 200ms ease-out';
                            el.style.width = `${collapsedWidth}px`;
                            el.addEventListener(
                                'transitionend',
                                () => {
                                    el.style.transition = 'none';
                                },
                                { once: true },
                            );
                        }
                        dragCollapsed = true;
                        return;
                    }

                    if (dragCollapsed && rawWidth >= collapseThreshold) {
                        dragCollapsed = false;
                        if (panelRef.current) {
                            panelRef.current.style.transition = 'none';
                        }
                    }
                }

                const newWidth = Math.min(
                    maxWidth,
                    Math.max(minWidth, rawWidth),
                );
                expandedWidthRef.current = newWidth;
                dragCollapsed = false;
                if (panelRef.current) {
                    panelRef.current.style.width = `${newWidth}px`;
                }
            };

            const onMouseUp = () => {
                if (collapsible && dragCollapsed) {
                    setIsCollapsed(true);
                    localStorage.setItem(collapsedKey, 'true');
                } else {
                    setIsCollapsed(false);
                    localStorage.setItem(collapsedKey, 'false');
                    setExpandedWidth(expandedWidthRef.current);
                    localStorage.setItem(
                        storageKey,
                        String(expandedWidthRef.current),
                    );
                }
                cleanup();
            };

            dragCleanupRef.current = cleanup;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },
        [
            collapsedKey,
            collapsedWidth,
            collapseThreshold,
            collapsible,
            direction,
            maxWidth,
            minWidth,
            storageKey,
        ],
    );

    const width = collapsible && isCollapsed ? collapsedWidth : expandedWidth;

    return { width, isCollapsed, toggleCollapsed, panelRef, handleMouseDown };
}

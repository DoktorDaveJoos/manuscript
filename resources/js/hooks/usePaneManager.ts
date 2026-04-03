import { useCallback, useRef, useState } from 'react';

export type Pane = {
    id: string; // unique instance ID for React keys (NOT chapter ID)
    chapterId: number;
};

let paneIdCounter = 0;
function nextPaneId(): string {
    return `pane-${++paneIdCounter}`;
}

function panesFromQuery(
    query: string | null,
    fallbackChapterId?: number,
): Pane[] {
    if (query) {
        const ids = query.split(',').map(Number).filter(Boolean);
        if (ids.length > 0) {
            return ids.map((chapterId) => ({ id: nextPaneId(), chapterId }));
        }
    }
    if (fallbackChapterId) {
        return [{ id: nextPaneId(), chapterId: fallbackChapterId }];
    }
    return [];
}

function syncUrl(bookId: number, panes: Pane[]) {
    const paneIds = panes.map((p) => p.chapterId).join(',');
    const url = `/books/${bookId}/editor${panes.length ? `?panes=${paneIds}` : ''}`;
    window.history.replaceState({}, '', url);
}

export default function usePaneManager(
    bookId: number,
    initialQuery: string | null,
    fallbackChapterId?: number,
) {
    const [panes, setPanes] = useState<Pane[]>(() =>
        panesFromQuery(initialQuery, fallbackChapterId),
    );
    const [focusedPaneId, setFocusedPaneId] = useState<string | null>(
        () => panes[0]?.id ?? null,
    );

    const panesRef = useRef(panes);
    panesRef.current = panes;

    const updatePanes = useCallback(
        (updater: (prev: Pane[]) => Pane[]) => {
            setPanes((prev) => {
                const next = updater(prev);
                panesRef.current = next;
                syncUrl(bookId, next);
                return next;
            });
        },
        [bookId],
    );

    // Open chapter in new pane (focus existing if already open)
    const openInNewPane = useCallback(
        (chapterId: number) => {
            const existing = panesRef.current.find(
                (p) => p.chapterId === chapterId,
            );
            if (existing) {
                setFocusedPaneId(existing.id);
                return;
            }
            const newPane: Pane = { id: nextPaneId(), chapterId };
            updatePanes((prev) => [...prev, newPane]);
            setFocusedPaneId(newPane.id);
        },
        [updatePanes],
    );

    // Navigate: replace focused pane's chapter (or focus existing if already open)
    const navigateToChapter = useCallback(
        (chapterId: number) => {
            const existing = panesRef.current.find(
                (p) => p.chapterId === chapterId,
            );
            if (existing) {
                setFocusedPaneId(existing.id);
                return;
            }
            if (!focusedPaneId) {
                openInNewPane(chapterId);
                return;
            }
            updatePanes((prev) =>
                prev.map((p) =>
                    p.id === focusedPaneId ? { ...p, chapterId } : p,
                ),
            );
        },
        [focusedPaneId, openInNewPane, updatePanes],
    );

    // Close pane; focus shifts LEFT (always)
    const closePane = useCallback(
        (paneId: string) => {
            updatePanes((prev) => {
                const index = prev.findIndex((p) => p.id === paneId);
                const next = prev.filter((p) => p.id !== paneId);

                if (focusedPaneId === paneId) {
                    const newFocusIndex = Math.max(0, index - 1);
                    setFocusedPaneId(next[newFocusIndex]?.id ?? null);
                }

                return next;
            });
        },
        [focusedPaneId, updatePanes],
    );

    const focusedPane = panes.find((p) => p.id === focusedPaneId) ?? null;

    return {
        panes,
        focusedPaneId,
        focusedPane,
        setFocusedPaneId,
        openInNewPane,
        navigateToChapter,
        closePane,
    };
}

// Pane autosave flush helpers. Each ChapterPane attaches a `__flushPane`
// callback to its `data-pane-chapter` element; AI commit flows need to drain
// that buffer before snapshotting, since commits read scene rows (not the
// in-memory editor).

type FlushablePane = HTMLElement & { __flushPane?: () => Promise<void> };

function getFlush(el: Element | null): (() => Promise<void>) | null {
    const flush = (el as FlushablePane | null)?.__flushPane;
    return typeof flush === 'function' ? flush : null;
}

export async function flushPaneByChapter(chapterId: number): Promise<void> {
    const flush = getFlush(
        document.querySelector(`[data-pane-chapter="${chapterId}"]`),
    );
    if (flush) await flush();
}

export async function flushAllPanes(): Promise<void> {
    const els = document.querySelectorAll('[data-pane-chapter]');
    await Promise.all(
        Array.from(els).map((el) => getFlush(el)?.() ?? Promise.resolve()),
    );
}

import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';

const DEAD_ZONE = 2;
const LERP_SPEED = 0.18;
// Measured in document positions, not pixels. Selection moves ≤ this get
// instant scroll (caret pinned for typing/arrow keys); larger jumps animate.
const JUMP_THRESHOLD = 40;

let animationId: number | null = null;
let targetScrollTop: number | null = null;
let isMouseDriven = false;
let mouseUpTimerId: ReturnType<typeof setTimeout> | null = null;
let activeInstances = 0;

export function cancelTypewriterAnimation(): void {
    if (animationId !== null) {
        cancelAnimationFrame(animationId);
        animationId = null;
    }
    targetScrollTop = null;
}

export function centerCursorInContainer(
    view: EditorView,
    container: HTMLElement,
    instant: boolean,
): void {
    const { head } = view.state.selection;
    let coords: { top: number };
    try {
        coords = view.coordsAtPos(head);
    } catch {
        return;
    }
    const containerRect = container.getBoundingClientRect();
    const cursorRelativeY = coords.top - containerRect.top;
    const targetY = containerRect.height / 2;
    const delta = cursorRelativeY - targetY;

    if (Math.abs(delta) <= DEAD_ZONE) return;

    const maxScroll = container.scrollHeight - container.clientHeight;
    const desired = Math.max(
        0,
        Math.min(container.scrollTop + delta, maxScroll),
    );

    if (Math.abs(desired - container.scrollTop) < 0.5) return;

    if (instant) {
        cancelTypewriterAnimation();
        container.scrollTop = desired;
        return;
    }

    targetScrollTop = desired;

    if (animationId === null) {
        const step = () => {
            if (targetScrollTop === null) {
                animationId = null;
                return;
            }

            const diff = targetScrollTop - container.scrollTop;
            if (Math.abs(diff) < 0.5) {
                container.scrollTop = targetScrollTop;
                targetScrollTop = null;
                animationId = null;
                return;
            }

            container.scrollTop += diff * LERP_SPEED;
            animationId = requestAnimationFrame(step);
        };
        animationId = requestAnimationFrame(step);
    }
}

// IMPORTANT: TipTap's `configure()` uses `mergeDeep` on options, which
// recursively clones plain objects. React refs (`{ current: ... }`) are plain
// objects, so passing them here would disconnect the plugin from React's
// updates. We pass GETTER FUNCTIONS instead — functions aren't plain objects,
// so mergeDeep replaces them atomically, and each call reads the live state.
export const TypewriterScrollExtension = Extension.create<{
    isEnabled: () => boolean;
    getScrollContainer: () => HTMLElement | null;
}>({
    name: 'typewriterScroll',

    addOptions() {
        return {
            isEnabled: () => false,
            getScrollContainer: () => null,
        };
    },

    addProseMirrorPlugins() {
        const { isEnabled, getScrollContainer } = this.options;
        let lastHead: number | null = null;

        return [
            new Plugin({
                key: new PluginKey('typewriterScroll'),
                props: {
                    handleScrollToSelection() {
                        // Suppress PM's built-in scroll when we're in control.
                        return isEnabled();
                    },
                    handleDOMEvents: {
                        mousedown() {
                            isMouseDriven = true;
                            return false;
                        },
                        mouseup() {
                            if (mouseUpTimerId !== null)
                                clearTimeout(mouseUpTimerId);
                            mouseUpTimerId = setTimeout(() => {
                                isMouseDriven = false;
                                mouseUpTimerId = null;
                            }, 50);
                            return false;
                        },
                    },
                },
                view() {
                    activeInstances++;
                    return {
                        update(view, prevState) {
                            if (!isEnabled()) {
                                lastHead = null;
                                return;
                            }
                            if (view.composing) return;
                            if (isMouseDriven) {
                                lastHead = view.state.selection.head;
                                return;
                            }

                            if (
                                view.state.selection.eq(prevState.selection) &&
                                view.state.doc.eq(prevState.doc)
                            ) {
                                return;
                            }

                            const container = getScrollContainer();
                            if (!container) return;

                            const head = view.state.selection.head;
                            const jump =
                                lastHead === null
                                    ? 0
                                    : Math.abs(head - lastHead);
                            lastHead = head;

                            const instant = jump <= JUMP_THRESHOLD;
                            centerCursorInContainer(view, container, instant);
                        },
                        destroy() {
                            activeInstances--;
                            if (activeInstances <= 0) {
                                activeInstances = 0;
                                if (mouseUpTimerId !== null) {
                                    clearTimeout(mouseUpTimerId);
                                    mouseUpTimerId = null;
                                }
                                isMouseDriven = false;
                                lastHead = null;
                                cancelTypewriterAnimation();
                            }
                        },
                    };
                },
            }),
        ];
    },
});

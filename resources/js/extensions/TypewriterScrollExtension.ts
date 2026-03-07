import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';
import type { RefObject } from 'react';

const DEAD_ZONE = 2;
const LERP_SPEED = 0.18;

// Module-level animation state — shared across all editor instances
// since they all scroll the same container.
let animationId: number | null = null;
let targetScrollTop: number | null = null;
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
    const { from } = view.state.selection;
    let coords: { top: number };
    try {
        coords = view.coordsAtPos(from);
    } catch {
        return;
    }
    const containerRect = container.getBoundingClientRect();
    const cursorRelativeY = coords.top - containerRect.top;
    const targetY = containerRect.height / 2;
    const delta = cursorRelativeY - targetY;

    if (Math.abs(delta) <= DEAD_ZONE) return;

    const maxScroll = container.scrollHeight - container.clientHeight;
    const desired = Math.max(0, Math.min(container.scrollTop + delta, maxScroll));

    if (instant) {
        cancelTypewriterAnimation();
        container.scrollTop = desired;
        return;
    }

    // Retarget the in-flight animation (or start a new one)
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

export const TypewriterScrollExtension = Extension.create<{
    scrollContainerRef: RefObject<HTMLDivElement | null>;
    enabledRef: RefObject<boolean>;
}>({
    name: 'typewriterScroll',

    addOptions() {
        return {
            scrollContainerRef: { current: null },
            enabledRef: { current: false },
        };
    },

    addProseMirrorPlugins() {
        const { scrollContainerRef, enabledRef } = this.options;

        return [
            new Plugin({
                key: new PluginKey('typewriterScroll'),
                props: {
                    handleScrollToSelection() {
                        if (!enabledRef.current) return false;
                        // Suppress ProseMirror's built-in scrollIntoView;
                        // centering is handled by the view() lifecycle below.
                        return true;
                    },
                },
                view(editorView) {
                    const onSelectionChange = () => {
                        if (!enabledRef.current || !editorView.hasFocus()) return;
                        requestAnimationFrame(() => {
                            if (!enabledRef.current || !editorView.hasFocus()) return;
                            const container = scrollContainerRef.current;
                            if (!container) return;
                            centerCursorInContainer(editorView, container, false);
                        });
                    };

                    document.addEventListener('selectionchange', onSelectionChange);

                    return {
                        update(view, prevState) {
                            if (!enabledRef.current) return;

                            // Only center when selection or document changed
                            if (
                                view.state.selection.eq(prevState.selection) &&
                                view.state.doc.eq(prevState.doc)
                            ) {
                                return;
                            }

                            // Defer to next frame so DOM coordinates are settled
                            // after native cursor movement
                            requestAnimationFrame(() => {
                                if (!enabledRef.current) return;
                                const container = scrollContainerRef.current;
                                if (!container) return;
                                centerCursorInContainer(view, container, false);
                            });
                        },
                        destroy() {
                            document.removeEventListener('selectionchange', onSelectionChange);
                            cancelTypewriterAnimation();
                        },
                    };
                },
            }),
        ];
    },
});

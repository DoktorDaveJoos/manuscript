import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import type { EditorView } from '@tiptap/pm/view';
import type { RefObject } from 'react';

const DEAD_ZONE = 2;
const LERP_SPEED = 0.18;

// Module-level state — shared across all editor instances
// since they all scroll the same container.
let animationId: number | null = null;
let targetScrollTop: number | null = null;
let isMouseDriven = false;
let mouseUpTimerId: ReturnType<typeof setTimeout> | null = null;
let pendingUpdateRaf: number | null = null;
const activeInstances = 0;

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
    // Track the moving end of the selection, not the anchor.
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

    // Avoid starting a no-op animation on sub-pixel rounding differences
    if (Math.abs(desired - container.scrollTop) < 0.5) return;

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
                        return true;
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
                            if (!enabledRef.current) return;
                            if (isMouseDriven) return;

                            if (
                                view.state.selection.eq(prevState.selection) &&
                                view.state.doc.eq(prevState.doc)
                            ) {
                                return;
                            }

                            // Deduplicate rapid transactions within the same frame
                            if (pendingUpdateRaf !== null)
                                cancelAnimationFrame(pendingUpdateRaf);
                            pendingUpdateRaf = requestAnimationFrame(() => {
                                pendingUpdateRaf = null;
                                if (!enabledRef.current) return;
                                // Re-check: mouse click may have occurred between
                                // the synchronous check above and this rAF callback.
                                if (isMouseDriven) return;
                                const container = scrollContainerRef.current;
                                if (!container) return;
                                centerCursorInContainer(view, container, false);
                            });
                        },
                        destroy() {
                            activeInstances--;
                            if (activeInstances <= 0) {
                                activeInstances = 0;
                                if (pendingUpdateRaf !== null) {
                                    cancelAnimationFrame(pendingUpdateRaf);
                                    pendingUpdateRaf = null;
                                }
                                if (mouseUpTimerId !== null) {
                                    clearTimeout(mouseUpTimerId);
                                    mouseUpTimerId = null;
                                }
                                isMouseDriven = false;
                                cancelTypewriterAnimation();
                            }
                        },
                    };
                },
            }),
        ];
    },
});

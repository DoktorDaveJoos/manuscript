import { useCallback, useEffect, useState } from 'react';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { cn } from '@/lib/utils';

export default function SlidePanel({
    open,
    onClose,
    storageKey,
    defaultWidth,
    minWidth = 200,
    maxWidth = 600,
    children,
}: {
    open: boolean;
    onClose: () => void;
    storageKey: string;
    defaultWidth: number;
    minWidth?: number;
    maxWidth?: number;
    children: React.ReactNode;
}) {
    const [mounted, setMounted] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [settled, setSettled] = useState(false);

    const {
        width: panelWidth,
        panelRef,
        handleMouseDown,
    } = useResizablePanel({
        storageKey,
        minWidth,
        maxWidth,
        defaultWidth,
        direction: 'right',
    });

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                onClose();
            }
        },
        [onClose],
    );

    useEffect(() => {
        if (open) {
            setMounted(true);
            setSettled(false);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => setExpanded(true));
            });
        } else {
            if (settled) {
                setSettled(false);
                requestAnimationFrame(() => setExpanded(false));
            } else {
                setExpanded(false);
            }
        }
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!mounted) return null;

    const isAnimating = !settled;

    return (
        <div
            ref={settled ? panelRef : undefined}
            style={{
                width: isAnimating
                    ? expanded
                        ? panelWidth
                        : 0
                    : panelWidth,
            }}
            className={cn('h-full shrink-0', isAnimating ? 'overflow-hidden transition-[width] duration-200 ease-out' : 'relative')}
            onKeyDown={handleKeyDown}
            onTransitionEnd={
                isAnimating
                    ? () => {
                          if (open) {
                              setSettled(true);
                          } else {
                              setMounted(false);
                          }
                      }
                    : undefined
            }
        >
            <div
                onMouseDown={settled ? handleMouseDown : undefined}
                className={cn('group absolute inset-y-0 -left-1 z-10 w-2', settled ? 'cursor-col-resize' : 'pointer-events-none opacity-0')}
            >
                <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
            </div>
            <div
                className="h-full"
                style={isAnimating ? { minWidth: panelWidth } : undefined}
            >
                {children}
            </div>
        </div>
    );
}

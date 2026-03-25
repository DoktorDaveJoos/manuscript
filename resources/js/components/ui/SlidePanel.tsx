import { useCallback, useEffect, useState } from 'react';
import { useResizablePanel } from '@/hooks/useResizablePanel';

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

    if (settled) {
        return (
            <div
                ref={panelRef}
                className="relative h-full shrink-0"
                style={{ width: panelWidth }}
                onKeyDown={handleKeyDown}
            >
                <div
                    onMouseDown={handleMouseDown}
                    className="group absolute inset-y-0 -left-1 z-10 w-2 cursor-col-resize"
                >
                    <div className="absolute inset-y-0 left-[3px] w-px bg-transparent transition-colors group-hover:bg-ink/20" />
                </div>
                {children}
            </div>
        );
    }

    return (
        <div
            style={{ width: expanded ? panelWidth : 0 }}
            className="h-full shrink-0 overflow-hidden transition-[width] duration-200 ease-out"
            onKeyDown={handleKeyDown}
            onTransitionEnd={() => {
                if (open) {
                    setSettled(true);
                } else {
                    setMounted(false);
                }
            }}
        >
            <div className="h-full" style={{ minWidth: panelWidth }}>
                {children}
            </div>
        </div>
    );
}

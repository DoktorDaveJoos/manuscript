import type { Problem } from 'prosemirror-proofread';
import { createRoot } from 'react-dom/client';

interface SuggestionPopoverProps {
    error: Problem;
    onIgnore: () => void;
    onClose: () => void;
}

function SuggestionPopoverContent({
    error,
    onIgnore,
    onClose,
}: SuggestionPopoverProps) {
    return (
        <div
            className="w-[240px] rounded-lg border border-border bg-surface-card p-2 shadow-[0_4px_16px_#1A1A1A12,0_1px_4px_#1A1A1A08]"
            onKeyDown={(e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    onClose();
                }
            }}
        >
            <p className="px-1 pb-1.5 text-[12px] leading-snug text-ink-muted">
                {error.msg}
            </p>

            <div className="flex items-center gap-1 border-t border-border pt-1.5">
                <button
                    type="button"
                    onClick={onIgnore}
                    className="rounded-md px-2 py-0.5 text-[11px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    Ignore
                </button>
            </div>
        </div>
    );
}

export function createSuggestionBoxElement(options: {
    error: Problem;
    position: { x: number; y: number };
    onReplace: (value: string) => void;
    onIgnore: () => void;
    onClose: () => void;
}): { destroy: () => void } {
    const container = document.createElement('div');
    container.style.position = 'absolute';
    container.style.zIndex = '50';
    container.style.left = `${options.position.x}px`;
    container.style.top = `${options.position.y}px`;

    const root = createRoot(container);

    let cleaned = false;
    const handleClickOutside = (e: MouseEvent) => {
        if (!container.contains(e.target as Node)) {
            cleanup();
        }
    };
    const cleanup = () => {
        if (cleaned) return;
        cleaned = true;
        document.removeEventListener('mousedown', handleClickOutside);
        root.unmount();
        container.remove();
    };

    root.render(
        <SuggestionPopoverContent
            error={options.error}
            onIgnore={() => {
                options.onIgnore();
                cleanup();
            }}
            onClose={() => {
                options.onClose();
                cleanup();
            }}
        />,
    );

    document.body.appendChild(container);

    requestAnimationFrame(() => {
        if (!cleaned) {
            document.addEventListener('mousedown', handleClickOutside);
        }
    });

    return { destroy: cleanup };
}

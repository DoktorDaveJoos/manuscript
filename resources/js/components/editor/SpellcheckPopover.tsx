import i18n from 'i18next';
import { createRoot } from 'react-dom/client';

interface SpellcheckPopoverProps {
    suggestions: string[];
    onReplace: (replacement: string) => void;
    onAddToDictionary: () => void;
    onClose: () => void;
}

function SpellcheckPopoverContent({
    suggestions,
    onReplace,
    onAddToDictionary,
    onClose,
}: SpellcheckPopoverProps) {
    return (
        <div
            className="w-[200px] rounded-lg border border-border bg-surface-card p-1 shadow-[0_4px_16px_#1A1A1A12,0_1px_4px_#1A1A1A08]"
            onKeyDown={(e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    onClose();
                }
            }}
        >
            {suggestions.length > 0 ? (
                suggestions.slice(0, 5).map((s) => (
                    <button
                        key={s}
                        type="button"
                        onClick={() => onReplace(s)}
                        className="flex w-full rounded-md px-2 py-1 text-left text-[13px] text-ink transition-colors hover:bg-neutral-bg"
                    >
                        {s}
                    </button>
                ))
            ) : (
                <span className="block px-2 py-1 text-[12px] text-ink-faint">
                    {i18n.t('editor:spellcheck.noSuggestions')}
                </span>
            )}
            <div className="my-1 border-t border-border" />
            <button
                type="button"
                onClick={onAddToDictionary}
                className="flex w-full rounded-md px-2 py-1 text-left text-[12px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                {i18n.t('editor:spellcheck.addToDictionary')}
            </button>
        </div>
    );
}

export function createSpellcheckPopover(options: {
    suggestions: string[];
    position: { x: number; y: number };
    onReplace: (replacement: string) => void;
    onAddToDictionary: () => void;
}): { destroy: () => void } {
    const container = document.createElement('div');
    container.style.position = 'fixed';
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
        <SpellcheckPopoverContent
            suggestions={options.suggestions}
            onReplace={(replacement) => {
                options.onReplace(replacement);
                cleanup();
            }}
            onAddToDictionary={() => {
                options.onAddToDictionary();
                cleanup();
            }}
            onClose={cleanup}
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

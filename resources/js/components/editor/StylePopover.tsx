import i18n from 'i18next';
import { createRoot } from 'react-dom/client';
import type { StyleFinding } from '@/lib/style/types';

interface StylePopoverProps {
    finding: StyleFinding;
    onIgnoreWord?: () => void;
    onDeleteWord?: () => void;
    onClose: () => void;
}

function StylePopoverContent({
    finding,
    onIgnoreWord,
    onDeleteWord,
    onClose,
}: StylePopoverProps) {
    const hasActions = Boolean(onIgnoreWord || onDeleteWord);
    return (
        <div
            className="w-[240px] rounded-lg border border-border bg-surface-card p-1 shadow-[0_4px_16px_#1A1A1A12,0_1px_4px_#1A1A1A08]"
            onKeyDown={(e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    onClose();
                }
            }}
        >
            <div className="px-2 py-1">
                <span className="text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                    {i18n.t(`editor:style.category.${finding.category}`)}
                </span>
                <p className="mt-0.5 text-[12px] text-ink-soft">
                    {i18n.t(`editor:style.explanation.${finding.category}`)}
                </p>
                {finding.partner && (
                    <p className="mt-0.5 text-[12px] text-ink-faint">
                        {i18n.t('editor:style.repetitionPartner', {
                            word: finding.partner.word,
                        })}
                    </p>
                )}
            </div>
            {hasActions && <div className="my-1 border-t border-border" />}
            {onDeleteWord && (
                <button
                    type="button"
                    data-style-action="delete"
                    onClick={onDeleteWord}
                    className="flex w-full rounded-md px-2 py-1 text-left text-[12px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    {i18n.t('editor:style.deleteWord')}
                </button>
            )}
            {onIgnoreWord && (
                <button
                    type="button"
                    data-style-action="ignore"
                    onClick={onIgnoreWord}
                    className="flex w-full rounded-md px-2 py-1 text-left text-[12px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    {i18n.t('editor:style.ignoreWord')}
                </button>
            )}
        </div>
    );
}

export function createStylePopover(options: {
    finding: StyleFinding;
    position: { x: number; y: number };
    onIgnoreWord?: () => void;
    onDeleteWord?: () => void;
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
        <StylePopoverContent
            finding={options.finding}
            onIgnoreWord={
                options.onIgnoreWord &&
                (() => {
                    options.onIgnoreWord?.();
                    cleanup();
                })
            }
            onDeleteWord={
                options.onDeleteWord &&
                (() => {
                    options.onDeleteWord?.();
                    cleanup();
                })
            }
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

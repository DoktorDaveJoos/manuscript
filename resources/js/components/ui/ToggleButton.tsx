import { memo } from 'react';
import { cn } from '@/lib/utils';

const ToggleButton = memo(function ToggleButton({
    label,
    active,
    onClick,
    title,
    mono,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
    title: string;
    mono?: boolean;
}) {
    return (
        <button
            onClick={onClick}
            title={title}
            className={cn(
                'flex size-6 items-center justify-center rounded text-[11px] font-semibold transition-colors',
                mono && 'font-mono',
                active
                    ? 'bg-neutral-bg text-ink'
                    : 'text-ink-faint hover:bg-neutral-bg hover:text-ink',
            )}
        >
            {label}
        </button>
    );
});

export default ToggleButton;

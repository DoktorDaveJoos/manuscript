import { cn } from '@/lib/utils';
import type { PropsWithChildren } from 'react';

type DialogProps = PropsWithChildren<{
    onClose: () => void;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
}>;

const backdropColors = { none: '', light: 'bg-ink/[0.08]', dark: 'bg-black/20' };

export default function Dialog({ onClose, width = 480, backdrop = 'dark', className, children }: DialogProps) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className={cn('absolute inset-0', backdropColors[backdrop])} onClick={onClose} />
            <div
                className={cn(
                    'relative z-10 flex flex-col rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]',
                    className,
                )}
                style={width ? { width } : undefined}
            >
                {children}
            </div>
        </div>
    );
}

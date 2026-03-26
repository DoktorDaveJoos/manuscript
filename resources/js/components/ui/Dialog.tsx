import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type DialogProps = PropsWithChildren<{
    onClose: () => void;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
    title?: string;
}>;

const backdropColors = {
    none: 'bg-transparent',
    light: 'bg-ink/[0.08]',
    dark: 'bg-black/20',
};

export default function Dialog({ onClose, width = 480, backdrop = 'dark', className, title = 'Dialog', children }: DialogProps) {
    return (
        <DialogPrimitive.Root open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className={cn('fixed inset-0 z-50', backdropColors[backdrop])} />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    className={cn(
                        'fixed top-1/2 left-1/2 z-50 flex -translate-x-1/2 -translate-y-1/2 flex-col rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]',
                        className,
                    )}
                    style={width ? { width } : undefined}
                >
                    <DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>
                    {children}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

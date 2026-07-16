import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type DialogProps = PropsWithChildren<{
    onClose: () => void;
    title: string;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
    closeOnOutsideClick?: boolean;
}>;

const backdropColors = {
    none: 'bg-transparent',
    light: 'bg-ink/[0.08]',
    dark: 'bg-ink/20',
};

export default function Dialog({
    onClose,
    title,
    width = 480,
    backdrop = 'dark',
    className,
    closeOnOutsideClick = true,
    children,
}: DialogProps) {
    return (
        <DialogPrimitive.Root
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay
                    className={cn('fixed inset-0 z-50', backdropColors[backdrop])}
                />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onInteractOutside={
                        closeOnOutsideClick
                            ? undefined
                            : (event) => event.preventDefault()
                    }
                    className={cn(
                        'fixed top-1/2 left-1/2 z-50 flex -translate-x-1/2 -translate-y-1/2 flex-col rounded-xl bg-surface-card p-10 shadow-xl',
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

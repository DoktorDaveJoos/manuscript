import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type DrawerProps = PropsWithChildren<{
    onClose: () => void;
    title: string;
    className?: string;
}>;

export default function Drawer({ onClose, title, className, children }: DrawerProps) {
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
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-ink/[0.05]" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    className={cn(
                        'fixed top-0 right-0 bottom-0 z-50 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-xl animate-[slideInRight_150ms_ease-out]',
                        className,
                    )}
                >
                    <DialogPrimitive.Title className="sr-only">
                        {title}
                    </DialogPrimitive.Title>
                    {children}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

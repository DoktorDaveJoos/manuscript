import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

type DrawerProps = PropsWithChildren<{
    onClose: () => void;
    className?: string;
}>;

export default function Drawer({ onClose, className, children }: DrawerProps) {
    return (
        <DialogPrimitive.Root open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/5" />
                <DialogPrimitive.Content
                    className={cn(
                        'fixed top-0 right-0 bottom-0 z-50 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]',
                        className,
                    )}
                >
                    {children}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

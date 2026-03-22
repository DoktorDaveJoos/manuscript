import * as DialogPrimitive from '@radix-ui/react-dialog';
import { forwardRef, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const Sheet = DialogPrimitive.Root;
const SheetTrigger = DialogPrimitive.Trigger;
const SheetClose = DialogPrimitive.Close;
const SheetPortal = DialogPrimitive.Portal;

const SheetOverlay = forwardRef<HTMLDivElement, DialogPrimitive.DialogOverlayProps>(
    ({ className, ...props }, ref) => (
        <DialogPrimitive.Overlay
            ref={ref}
            className={cn('fixed inset-0 z-50 bg-black/5', className)}
            {...props}
        />
    ),
);
SheetOverlay.displayName = 'SheetOverlay';

const sheetContentBase =
    'fixed top-0 right-0 bottom-0 z-50 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]';

const SheetContent = forwardRef<HTMLDivElement, DialogPrimitive.DialogContentProps>(
    ({ className, children, ...props }, ref) => (
        <SheetPortal>
            <SheetOverlay />
            <DialogPrimitive.Content
                ref={ref}
                className={cn(sheetContentBase, className)}
                {...props}
            >
                {children}
            </DialogPrimitive.Content>
        </SheetPortal>
    ),
);
SheetContent.displayName = 'SheetContent';

const SheetTitle = DialogPrimitive.Title;
const SheetDescription = DialogPrimitive.Description;

type LegacyDrawerProps = PropsWithChildren<{
    onClose: () => void;
    className?: string;
}>;

function LegacyDrawer({ onClose, className, children }: LegacyDrawerProps) {
    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetPortal>
                <SheetOverlay />
                <DialogPrimitive.Content className={cn(sheetContentBase, className)}>
                    {children}
                </DialogPrimitive.Content>
            </SheetPortal>
        </Sheet>
    );
}

export default LegacyDrawer;
export {
    Sheet,
    SheetTrigger,
    SheetClose,
    SheetPortal,
    SheetOverlay,
    SheetContent,
    SheetTitle,
    SheetDescription,
};
export type { LegacyDrawerProps };

import * as DialogPrimitive from '@radix-ui/react-dialog';
import { forwardRef, type ComponentPropsWithoutRef, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const Sheet = DialogPrimitive.Root;
const SheetTrigger = DialogPrimitive.Trigger;
const SheetPortal = DialogPrimitive.Portal;
const SheetClose = DialogPrimitive.Close;

const SheetOverlay = forwardRef<
    HTMLDivElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn('fixed inset-0 z-50 bg-black/5', className)}
        {...props}
    />
));
SheetOverlay.displayName = 'SheetOverlay';

const SheetContent = forwardRef<
    HTMLDivElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Content>
>(({ className, children, ...props }, ref) => (
    <SheetPortal>
        <SheetOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(
                'fixed top-0 right-0 bottom-0 z-50 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]',
                className,
            )}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </SheetPortal>
));
SheetContent.displayName = 'SheetContent';

function SheetHeader({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex flex-col gap-2', className)} {...props} />;
}

function SheetFooter({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex items-center justify-end gap-3', className)} {...props} />;
}

const SheetTitle = forwardRef<
    HTMLHeadingElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('text-sm font-medium text-ink', className)}
        {...props}
    />
));
SheetTitle.displayName = 'SheetTitle';

const SheetDescription = forwardRef<
    HTMLParagraphElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        className={cn('text-sm text-ink-muted', className)}
        {...props}
    />
));
SheetDescription.displayName = 'SheetDescription';

type LegacyDrawerProps = PropsWithChildren<{
    onClose: () => void;
    className?: string;
}>;

function LegacyDrawer({ onClose, className, children }: LegacyDrawerProps) {
    return (
        <Sheet open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <SheetContent className={className}>
                {children}
            </SheetContent>
        </Sheet>
    );
}

export default LegacyDrawer;
export {
    Sheet,
    SheetTrigger,
    SheetPortal,
    SheetClose,
    SheetOverlay,
    SheetContent,
    SheetHeader,
    SheetFooter,
    SheetTitle,
    SheetDescription,
};

import * as DialogPrimitive from '@radix-ui/react-dialog';
import { type ComponentPropsWithoutRef, forwardRef, type HTMLAttributes, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const sheetContentBase =
    'fixed top-0 right-0 bottom-0 z-50 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]';

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
                sheetContentBase,
                className,
            )}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </SheetPortal>
));
SheetContent.displayName = 'SheetContent';

function SheetHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex flex-col gap-2 p-4', className)} {...props} />;
}

function SheetFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('flex items-center justify-end gap-2 p-4', className)}
            {...props}
        />
    );
}

const SheetTitle = forwardRef<
    HTMLHeadingElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('text-lg font-semibold', className)}
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
        <DialogPrimitive.Root open={true} onOpenChange={(open) => !open && onClose()}>
            <SheetPortal>
                <SheetOverlay />
                <DialogPrimitive.Content
                    className={cn(
                        sheetContentBase,
                        className,
                    )}
                >
                    {children}
                </DialogPrimitive.Content>
            </SheetPortal>
        </DialogPrimitive.Root>
    );
}

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
export default LegacyDrawer;

import * as DialogPrimitive from '@radix-ui/react-dialog';
import { forwardRef, type PropsWithChildren, useEffect } from 'react';
import { cn } from '@/lib/utils';

const SheetRoot = DialogPrimitive.Root;
const SheetTrigger = DialogPrimitive.Trigger;
const SheetClose = DialogPrimitive.Close;
const SheetPortal = DialogPrimitive.Portal;

const SheetOverlay = forwardRef<
    HTMLDivElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
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
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content>
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

const SheetTitle = forwardRef<
    HTMLHeadingElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('text-lg font-semibold text-ink', className)}
        {...props}
    />
));
SheetTitle.displayName = 'SheetTitle';

const SheetDescription = forwardRef<
    HTMLParagraphElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
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
    useEffect(() => {
        function handleEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50">
            <div
                className="absolute inset-0 bg-black/5"
                onClick={onClose}
            />
            <aside
                className={cn(
                    'absolute top-0 right-0 bottom-0 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]',
                    className,
                )}
            >
                {children}
            </aside>
        </div>
    );
}

export {
    SheetRoot,
    SheetTrigger,
    SheetClose,
    SheetPortal,
    SheetOverlay,
    SheetContent,
    SheetTitle,
    SheetDescription,
};
export default LegacyDrawer;

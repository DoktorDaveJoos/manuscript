import * as DialogPrimitive from '@radix-ui/react-dialog';
import { type ComponentPropsWithoutRef, forwardRef, type HTMLAttributes, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const dialogContentBase =
    'fixed top-1/2 left-1/2 z-50 -translate-x-1/2 -translate-y-1/2 flex flex-col rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogPortal = DialogPrimitive.Portal;
const DialogClose = DialogPrimitive.Close;

const DialogOverlay = forwardRef<
    HTMLDivElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn('fixed inset-0 z-50 bg-black/20', className)}
        {...props}
    />
));
DialogOverlay.displayName = 'DialogOverlay';

const DialogContent = forwardRef<
    HTMLDivElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Content>
>(({ className, children, ...props }, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(dialogContentBase, className)}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = 'DialogContent';

function DialogHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('flex flex-col gap-2', className)} {...props} />;
}

function DialogFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('flex items-center justify-end gap-2', className)}
            {...props}
        />
    );
}

const DialogTitle = forwardRef<
    HTMLHeadingElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('font-serif text-[32px]', className)}
        {...props}
    />
));
DialogTitle.displayName = 'DialogTitle';

const DialogDescription = forwardRef<
    HTMLParagraphElement,
    ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        className={cn('text-sm text-ink-muted', className)}
        {...props}
    />
));
DialogDescription.displayName = 'DialogDescription';

const backdropColors = { none: '', light: 'bg-ink/[0.08]', dark: 'bg-black/20' };

type LegacyDialogProps = PropsWithChildren<{
    onClose: () => void;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
}>;

function LegacyDialog({
    onClose,
    width = 480,
    backdrop = 'dark',
    className,
    children,
}: LegacyDialogProps) {
    return (
        <DialogPrimitive.Root open={true} onOpenChange={(open) => !open && onClose()}>
            <DialogPortal>
                <DialogPrimitive.Overlay
                    className={cn('fixed inset-0 z-50', backdropColors[backdrop])}
                />
                <DialogPrimitive.Content
                    className={cn(dialogContentBase, className)}
                    style={width ? { width } : undefined}
                >
                    {children}
                </DialogPrimitive.Content>
            </DialogPortal>
        </DialogPrimitive.Root>
    );
}

export {
    Dialog,
    DialogTrigger,
    DialogPortal,
    DialogClose,
    DialogOverlay,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle,
    DialogDescription,
};
export default LegacyDialog;

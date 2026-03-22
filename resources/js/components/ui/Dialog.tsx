import * as DialogPrimitive from '@radix-ui/react-dialog';
import { forwardRef, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const DialogRoot = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogPortal = DialogPrimitive.Portal;
const DialogClose = DialogPrimitive.Close;

const DialogOverlay = forwardRef<
    HTMLDivElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
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
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> & {
        width?: number;
    }
>(({ className, width = 480, children, ...props }, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(
                'fixed top-1/2 left-1/2 z-50 -translate-x-1/2 -translate-y-1/2',
                'flex flex-col rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]',
                className,
            )}
            style={width ? { width } : undefined}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = 'DialogContent';

const DialogTitle = forwardRef<
    HTMLHeadingElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Title
        ref={ref}
        className={cn('text-lg font-semibold text-ink', className)}
        {...props}
    />
));
DialogTitle.displayName = 'DialogTitle';

const DialogDescription = forwardRef<
    HTMLParagraphElement,
    React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
    <DialogPrimitive.Description
        ref={ref}
        className={cn('text-sm text-ink-muted', className)}
        {...props}
    />
));
DialogDescription.displayName = 'DialogDescription';

type LegacyDialogProps = PropsWithChildren<{
    onClose: () => void;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
}>;

const backdropColors = { none: '', light: 'bg-ink/[0.08]', dark: 'bg-black/20' };

function LegacyDialog({ onClose, width = 480, backdrop = 'dark', className, children }: LegacyDialogProps) {
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

export {
    DialogRoot,
    DialogTrigger,
    DialogPortal,
    DialogClose,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
};
export default LegacyDialog;

import * as DialogPrimitive from '@radix-ui/react-dialog';
import { forwardRef, type PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogClose = DialogPrimitive.Close;
const DialogPortal = DialogPrimitive.Portal;

const backdropColors = { none: '', light: 'bg-ink/[0.08]', dark: 'bg-black/20' };
const contentBase =
    'fixed top-1/2 left-1/2 z-50 flex -translate-x-1/2 -translate-y-1/2 flex-col rounded-xl bg-surface-card p-10 shadow-[0_8px_40px_rgba(0,0,0,0.08)]';

const DialogOverlay = forwardRef<
    HTMLDivElement,
    DialogPrimitive.DialogOverlayProps & { backdrop?: 'none' | 'light' | 'dark' }
>(({ backdrop = 'dark', className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn('fixed inset-0 z-50', backdropColors[backdrop], className)}
        {...props}
    />
));
DialogOverlay.displayName = 'DialogOverlay';

const DialogContent = forwardRef<
    HTMLDivElement,
    DialogPrimitive.DialogContentProps & { width?: number }
>(({ width = 480, className, children, ...props }, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            className={cn(contentBase, className)}
            style={width ? { width } : undefined}
            {...props}
        >
            {children}
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = 'DialogContent';

const DialogTitle = DialogPrimitive.Title;
const DialogDescription = DialogPrimitive.Description;

type LegacyDialogProps = PropsWithChildren<{
    onClose: () => void;
    width?: number;
    backdrop?: 'none' | 'light' | 'dark';
    className?: string;
}>;

function LegacyDialog({ onClose, width = 480, backdrop = 'dark', className, children }: LegacyDialogProps) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogPortal>
                <DialogOverlay backdrop={backdrop} />
                <DialogPrimitive.Content
                    className={cn(contentBase, className)}
                    style={width ? { width } : undefined}
                >
                    {children}
                </DialogPrimitive.Content>
            </DialogPortal>
        </Dialog>
    );
}

export default LegacyDialog;
export {
    Dialog,
    DialogTrigger,
    DialogClose,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogDescription,
};
export type { LegacyDialogProps };

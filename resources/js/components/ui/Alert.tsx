import { cva, type VariantProps } from 'class-variance-authority';
import { AlertCircle, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

const alertVariants = cva(
    'relative flex gap-3 rounded-lg border p-4 text-[13px]',
    {
        variants: {
            variant: {
                default: 'border-border bg-surface-card text-ink-soft',
                info: 'border-accent/30 bg-accent/5 text-ink-soft',
                destructive: 'border-delete/30 bg-delete/5 text-ink-soft',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

const VARIANT_ICONS = {
    default: null,
    info: <Info size={16} className="mt-0.5 shrink-0 text-accent" />,
    destructive: <AlertCircle size={16} className="mt-0.5 shrink-0 text-delete" />,
};

type AlertProps = React.HTMLAttributes<HTMLDivElement> &
    VariantProps<typeof alertVariants>;

function Alert({ className, variant = 'default', children, ...props }: AlertProps) {
    return (
        <div role="alert" className={cn(alertVariants({ variant }), className)} {...props}>
            {VARIANT_ICONS[variant ?? 'default']}
            <div className="flex flex-1 flex-col gap-1">{children}</div>
        </div>
    );
}

function AlertTitle({ className, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
    return <h5 className={cn('text-[13px] font-semibold text-ink', className)} {...props} />;
}

function AlertDescription({ className, ...props }: React.HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-[12px] text-ink-muted', className)} {...props} />;
}

export { Alert, AlertTitle, AlertDescription };

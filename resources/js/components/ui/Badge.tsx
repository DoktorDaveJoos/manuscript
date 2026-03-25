import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium',
    {
        variants: {
            variant: {
                default: 'bg-ink/10 text-ink',
                secondary: 'bg-neutral-bg text-ink-muted',
                destructive: 'bg-delete/10 text-delete',
                warning: 'bg-accent/10 text-accent',
                outline: 'border border-border text-ink-muted',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

type BadgeProps = HTMLAttributes<HTMLSpanElement> &
    VariantProps<typeof badgeVariants>;

export default function Badge({ variant, className, ...props }: BadgeProps) {
    return (
        <span className={cn(badgeVariants({ variant }), className)} {...props} />
    );
}


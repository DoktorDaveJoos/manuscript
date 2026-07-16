import { Slot, type SlotProps } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const markerVariants = cva(
    "group/marker relative flex min-h-4 w-full items-center gap-2 text-left text-xs text-ink-muted [&_svg:not([class*='size-'])]:size-3.5 [a]:text-accent [a]:underline [a]:underline-offset-3 [a]:hover:text-accent-dark",
    {
        variants: {
            variant: {
                default: '',
                separator:
                    'before:mr-1 before:h-px before:min-w-0 before:flex-1 before:bg-border-light after:ml-1 after:h-px after:min-w-0 after:flex-1 after:bg-border-light',
                border: 'border-b border-border-light pb-2',
            },
        },
    },
);

function Marker({
    className,
    variant = 'default',
    asChild = false,
    ...props
}: ComponentProps<'div'> &
    VariantProps<typeof markerVariants> & {
        asChild?: boolean;
}) {
    const classes = cn(markerVariants({ variant, className }));

    if (asChild) {
        return (
            <Slot
                data-slot="marker"
                data-variant={variant}
                className={classes}
                {...(props as SlotProps)}
            />
        );
    }

    return (
        <div
            data-slot="marker"
            data-variant={variant}
            className={classes}
            {...props}
        />
    );
}

function MarkerIcon({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span
            data-slot="marker-icon"
            aria-hidden="true"
            className={cn(
                "size-3.5 shrink-0 [&_svg:not([class*='size-'])]:size-3.5",
                className,
            )}
            {...props}
        />
    );
}

function MarkerContent({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span
            data-slot="marker-content"
            className={cn(
                'min-w-0 wrap-break-word group-data-[variant=separator]/marker:flex-none group-data-[variant=separator]/marker:text-center *:[a]:text-accent *:[a]:underline *:[a]:underline-offset-3 *:[a]:hover:text-accent-dark',
                className,
            )}
            {...props}
        />
    );
}

export { Marker, MarkerIcon, MarkerContent, markerVariants };

import { Slot, type SlotProps } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function BubbleGroup({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            data-slot="bubble-group"
            className={cn('flex min-w-0 flex-col gap-2', className)}
            {...props}
        />
    );
}

const bubbleVariants = cva(
    'group/bubble relative flex w-fit max-w-[85%] min-w-0 flex-col gap-1 group-data-[align=end]/message:self-end data-[align=end]:self-end data-[variant=ghost]:max-w-full',
    {
        variants: {
            variant: {
                default:
                    '*:data-[slot=bubble-content]:bg-ink *:data-[slot=bubble-content]:text-surface-card',
                secondary:
                    '*:data-[slot=bubble-content]:border-border-light *:data-[slot=bubble-content]:bg-surface-card *:data-[slot=bubble-content]:text-ink',
                muted:
                    '*:data-[slot=bubble-content]:bg-neutral-bg *:data-[slot=bubble-content]:text-ink',
                tinted:
                    '*:data-[slot=bubble-content]:bg-accent-light *:data-[slot=bubble-content]:text-ink-warm',
                outline:
                    '*:data-[slot=bubble-content]:border-border *:data-[slot=bubble-content]:bg-transparent *:data-[slot=bubble-content]:text-ink',
                ghost:
                    'border-none *:data-[slot=bubble-content]:rounded-none *:data-[slot=bubble-content]:bg-transparent *:data-[slot=bubble-content]:p-0 *:data-[slot=bubble-content]:text-ink',
                destructive:
                    '*:data-[slot=bubble-content]:bg-delete-bg *:data-[slot=bubble-content]:text-delete',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

function Bubble({
    variant = 'default',
    align = 'start',
    className,
    ...props
}: ComponentProps<'div'> &
    VariantProps<typeof bubbleVariants> & {
        align?: 'start' | 'end';
    }) {
    return (
        <div
            data-slot="bubble"
            data-variant={variant}
            data-align={align}
            className={cn(bubbleVariants({ variant }), className)}
            {...props}
        />
    );
}

function BubbleContent({
    asChild = false,
    className,
    ...props
}: ComponentProps<'div'> & {
    asChild?: boolean;
}) {
    const classes = cn(
        'w-fit max-w-full min-w-0 overflow-hidden rounded-xl border border-transparent px-4 py-3 text-sm leading-relaxed wrap-break-word group-data-[align=end]/bubble:self-end [button]:text-left [button,a]:transition-colors [button,a]:outline-none [button,a]:focus-visible:border-accent [button,a]:focus-visible:ring-2 [button,a]:focus-visible:ring-accent/30',
        className,
    );

    if (asChild) {
        return (
            <Slot
                data-slot="bubble-content"
                className={classes}
                {...(props as SlotProps)}
            />
        );
    }

    return (
        <div
            data-slot="bubble-content"
            className={classes}
            {...props}
        />
    );
}

const bubbleReactionsVariants = cva(
    'absolute flex w-fit shrink-0 items-center justify-center gap-1 rounded-full bg-neutral-bg px-1.5 py-0.5 text-sm ring-2 ring-surface-card has-[button]:p-0',
    {
        variants: {
            side: {
                top: 'top-0 -translate-y-3/4',
                bottom: 'bottom-0 translate-y-3/4',
            },
            align: {
                start: 'left-3',
                end: 'right-3',
            },
        },
        defaultVariants: {
            side: 'bottom',
            align: 'end',
        },
    },
);

function BubbleReactions({
    side = 'bottom',
    align = 'end',
    className,
    ...props
}: ComponentProps<'div'> & {
    align?: 'start' | 'end';
    side?: 'top' | 'bottom';
}) {
    return (
        <div
            data-slot="bubble-reactions"
            data-align={align}
            data-side={side}
            className={cn(
                bubbleReactionsVariants({ side, align }),
                className,
            )}
            {...props}
        />
    );
}

export { BubbleGroup, Bubble, BubbleContent, BubbleReactions };

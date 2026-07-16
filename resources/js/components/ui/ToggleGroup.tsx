import * as ToggleGroupPrimitive from '@radix-ui/react-toggle-group';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function ToggleGroup({
    className,
    ...props
}: ComponentProps<typeof ToggleGroupPrimitive.Root>) {
    return (
        <ToggleGroupPrimitive.Root
            className={cn('flex flex-wrap gap-1', className)}
            {...props}
        />
    );
}

function ToggleGroupItem({
    className,
    variant = 'default',
    ...props
}: ComponentProps<typeof ToggleGroupPrimitive.Item> & {
    variant?: 'default' | 'pill';
}) {
    return (
        <ToggleGroupPrimitive.Item
            className={cn(
                variant === 'default'
                    ? [
                          'rounded-md px-4 py-1.5 text-xs transition-colors',
                          'bg-neutral-bg text-ink-muted hover:text-ink',
                          'data-[state=on]:bg-ink data-[state=on]:font-semibold data-[state=on]:text-surface',
                          'disabled:opacity-60 disabled:hover:text-ink-muted',
                      ]
                    : 'rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors disabled:opacity-60',
                className,
            )}
            {...props}
        />
    );
}

export { ToggleGroup, ToggleGroupItem };

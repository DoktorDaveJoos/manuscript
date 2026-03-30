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
    ...props
}: ComponentProps<typeof ToggleGroupPrimitive.Item>) {
    return (
        <ToggleGroupPrimitive.Item
            className={cn(
                'rounded-md px-4 py-[7px] text-[12px] transition-colors',
                'bg-neutral-bg text-ink-muted hover:text-ink',
                'data-[state=on]:bg-ink data-[state=on]:font-semibold data-[state=on]:text-surface',
                'disabled:opacity-60 disabled:hover:text-ink-muted',
                className,
            )}
            {...props}
        />
    );
}

export { ToggleGroup, ToggleGroupItem };

import * as PopoverPrimitive from '@radix-ui/react-popover';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';
import { menuContentBase, menuShadow } from './menu-primitives';

const Popover = PopoverPrimitive.Root;
const PopoverTrigger = PopoverPrimitive.Trigger;
const PopoverAnchor = PopoverPrimitive.Anchor;

function PopoverContent({
    className,
    align = 'start',
    sideOffset = 4,
    ...props
}: ComponentProps<typeof PopoverPrimitive.Content>) {
    return (
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    menuContentBase,
                    menuShadow,
                    'w-[var(--radix-popover-trigger-width)] p-0 outline-none',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    'data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    className,
                )}
                {...props}
            />
        </PopoverPrimitive.Portal>
    );
}

export { Popover, PopoverTrigger, PopoverContent, PopoverAnchor };

import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { ChevronRight } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    menuContentBase,
    menuItemBase,
    menuItemVariants,
    menuLabelBase,
    menuSeparatorBase,
    menuShadow,
} from './menu-primitives';

function DropdownMenu(
    props: ComponentProps<typeof DropdownMenuPrimitive.Root>,
) {
    return <DropdownMenuPrimitive.Root {...props} />;
}

function DropdownMenuTrigger({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Trigger>) {
    return (
        <DropdownMenuPrimitive.Trigger
            className={cn('outline-none', className)}
            {...props}
        />
    );
}

function DropdownMenuContent({
    className,
    sideOffset = 4,
    align = 'end',
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Content>) {
    return (
        <DropdownMenuPrimitive.Portal>
            <DropdownMenuPrimitive.Content
                sideOffset={sideOffset}
                align={align}
                className={cn(
                    menuContentBase,
                    'min-w-[180px]',
                    menuShadow,
                    className,
                )}
                {...props}
            />
        </DropdownMenuPrimitive.Portal>
    );
}

function DropdownMenuItem({
    className,
    variant = 'default',
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Item> & {
    variant?: 'default' | 'danger';
}) {
    return (
        <DropdownMenuPrimitive.Item
            className={cn(
                menuItemBase,
                menuItemVariants[variant],
                'data-[disabled]:cursor-not-allowed data-[disabled]:text-ink-faint',
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuSeparator({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Separator>) {
    return (
        <DropdownMenuPrimitive.Separator
            className={cn(menuSeparatorBase, className)}
            {...props}
        />
    );
}

function DropdownMenuGroup(
    props: ComponentProps<typeof DropdownMenuPrimitive.Group>,
) {
    return <DropdownMenuPrimitive.Group {...props} />;
}

function DropdownMenuLabel({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.Label>) {
    return (
        <DropdownMenuPrimitive.Label
            className={cn(
                'px-3 py-1.5',
                menuLabelBase,
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuSub(
    props: ComponentProps<typeof DropdownMenuPrimitive.Sub>,
) {
    return <DropdownMenuPrimitive.Sub {...props} />;
}

function DropdownMenuSubTrigger({
    className,
    children,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.SubTrigger>) {
    return (
        <DropdownMenuPrimitive.SubTrigger
            className={cn(
                menuItemBase,
                'justify-between',
                menuItemVariants.default,
                className,
            )}
            {...props}
        >
            {children as ReactNode}
            <ChevronRight size={10} strokeWidth={2.5} className="text-ink-faint" />
        </DropdownMenuPrimitive.SubTrigger>
    );
}

function DropdownMenuSubContent({
    className,
    ...props
}: ComponentProps<typeof DropdownMenuPrimitive.SubContent>) {
    return (
        <DropdownMenuPrimitive.Portal>
            <DropdownMenuPrimitive.SubContent
                className={cn(
                    menuContentBase,
                    'min-w-[180px]',
                    menuShadow,
                    className,
                )}
                {...props}
            />
        </DropdownMenuPrimitive.Portal>
    );
}

export {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
};

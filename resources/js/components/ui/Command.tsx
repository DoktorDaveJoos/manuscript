import { Command as CommandPrimitive } from 'cmdk';
import { Search } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

function Command({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive>) {
    return (
        <CommandPrimitive
            className={cn(
                'flex size-full flex-col overflow-hidden',
                className,
            )}
            {...props}
        />
    );
}

function CommandInput({
    className,
    children,
    ...props
}: ComponentProps<typeof CommandPrimitive.Input> & {
    children?: ReactNode;
}) {
    return (
        <div className="flex items-center gap-2.5 border-b border-border px-4 py-3.5">
            <Search size={16} className="shrink-0 text-ink-faint" />
            <CommandPrimitive.Input
                className={cn(
                    'min-w-0 flex-1 bg-transparent text-sm text-ink placeholder:text-ink-faint outline-none disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                {...props}
            />
            {children}
        </div>
    );
}

function CommandList({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive.List>) {
    return (
        <CommandPrimitive.List
            className={cn(
                'overflow-x-hidden overflow-y-auto',
                className,
            )}
            {...props}
        />
    );
}

function CommandEmpty({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive.Empty>) {
    return (
        <CommandPrimitive.Empty
            className={cn(
                'px-4 py-4 text-center text-sm text-ink-faint',
                className,
            )}
            {...props}
        />
    );
}

function CommandGroup({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive.Group>) {
    return (
        <CommandPrimitive.Group
            className={cn(
                'flex flex-col gap-1 px-1.5 py-1',
                '[&_[cmdk-group-heading]]:px-2.5 [&_[cmdk-group-heading]]:pt-1.5 [&_[cmdk-group-heading]]:pb-1 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:tracking-[0.06em] [&_[cmdk-group-heading]]:text-ink-faint [&_[cmdk-group-heading]]:uppercase',
                className,
            )}
            {...props}
        />
    );
}

function CommandItem({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive.Item>) {
    return (
        <CommandPrimitive.Item
            className={cn(
                'flex w-full cursor-default items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-sm leading-4 text-ink outline-none',
                'data-[selected=true]:bg-neutral-bg',
                'data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-40',
                className,
            )}
            {...props}
        />
    );
}

function CommandSeparator({
    className,
    ...props
}: ComponentProps<typeof CommandPrimitive.Separator>) {
    return (
        <CommandPrimitive.Separator
            className={cn('mx-2 h-px bg-border', className)}
            {...props}
        />
    );
}

function CommandShortcut({
    className,
    ...props
}: ComponentProps<'span'>) {
    return (
        <span
            className={cn(
                'ml-auto flex items-center gap-[3px] text-ink-faint',
                className,
            )}
            {...props}
        />
    );
}

export {
    Command,
    CommandInput,
    CommandList,
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandSeparator,
    CommandShortcut,
};

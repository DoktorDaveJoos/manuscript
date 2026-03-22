import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { ChevronRight } from 'lucide-react';
import { type ReactNode, useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';
const itemBase =
    'flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] leading-[18px] transition-colors outline-none';

type Position = { x: number; y: number };

function ContextMenuRoot({
    position,
    onClose,
    className,
    children,
}: {
    position: Position;
    onClose: () => void;
    className?: string;
    children: ReactNode;
}) {
    const triggerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        triggerRef.current?.click();
    }, []);

    return (
        <DropdownMenuPrimitive.Root open onOpenChange={(open) => { if (!open) onClose(); }}>
            <DropdownMenuPrimitive.Trigger asChild>
                <div
                    ref={triggerRef}
                    className="pointer-events-none fixed h-0 w-0"
                    style={{ left: position.x, top: position.y }}
                />
            </DropdownMenuPrimitive.Trigger>
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.Content
                    side="bottom"
                    align="start"
                    className={cn(
                        'z-50 w-[200px] rounded-lg bg-surface-card p-1',
                        menuShadow,
                        className,
                    )}
                >
                    {children}
                </DropdownMenuPrimitive.Content>
            </DropdownMenuPrimitive.Portal>
        </DropdownMenuPrimitive.Root>
    );
}

function Item({
    icon,
    label,
    variant = 'default',
    disabled = false,
    onClick,
    className,
    children,
}: {
    icon?: ReactNode;
    label?: string;
    variant?: 'default' | 'danger';
    disabled?: boolean;
    onClick?: () => void;
    className?: string;
    children?: ReactNode;
}) {
    const variantStyles =
        variant === 'danger'
            ? disabled
                ? 'cursor-not-allowed text-ink-faint'
                : 'font-medium text-delete hover:bg-neutral-bg data-[highlighted]:bg-neutral-bg'
            : disabled
              ? 'cursor-not-allowed text-ink-faint'
              : 'text-ink-soft hover:bg-neutral-bg data-[highlighted]:bg-neutral-bg';

    return (
        <DropdownMenuPrimitive.Item
            disabled={disabled}
            onSelect={onClick}
            className={cn(itemBase, variantStyles, className)}
        >
            {children ?? (
                <>
                    {icon}
                    {label}
                </>
            )}
        </DropdownMenuPrimitive.Item>
    );
}

function Submenu({
    icon,
    label,
    width = 'w-[180px]',
    children,
}: {
    icon?: ReactNode;
    label: string;
    width?: string;
    children: ReactNode;
}) {
    return (
        <DropdownMenuPrimitive.Sub>
            <DropdownMenuPrimitive.SubTrigger
                className={cn(itemBase, 'justify-between text-ink-soft hover:bg-neutral-bg data-[highlighted]:bg-neutral-bg')}
            >
                <span className="flex items-center gap-2.5">
                    {icon}
                    {label}
                </span>
                <ChevronRight
                    size={10}
                    strokeWidth={2.5}
                    className="text-ink-faint"
                />
            </DropdownMenuPrimitive.SubTrigger>
            <DropdownMenuPrimitive.Portal>
                <DropdownMenuPrimitive.SubContent
                    className={cn(
                        'z-50 rounded-lg bg-surface-card p-1',
                        menuShadow,
                        width,
                    )}
                >
                    {children}
                </DropdownMenuPrimitive.SubContent>
            </DropdownMenuPrimitive.Portal>
        </DropdownMenuPrimitive.Sub>
    );
}

function Separator() {
    return <DropdownMenuPrimitive.Separator className="mx-2 my-1 h-px bg-border" />;
}

const ContextMenu = Object.assign(ContextMenuRoot, {
    Item,
    Submenu,
    Separator,
});

export default ContextMenu;

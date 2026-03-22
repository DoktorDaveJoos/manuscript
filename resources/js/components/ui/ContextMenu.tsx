import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { ChevronRight } from 'lucide-react';
import { type ReactNode } from 'react';
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
    return (
        <DropdownMenu.Root open={true} onOpenChange={(open) => !open && onClose()}>
            <DropdownMenu.Trigger asChild>
                <div
                    style={{
                        position: 'fixed',
                        left: position.x,
                        top: position.y,
                        width: 1,
                        height: 1,
                    }}
                />
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content
                    side="bottom"
                    align="start"
                    sideOffset={0}
                    className={cn(
                        'z-50 w-[200px] rounded-lg bg-surface-card',
                        menuShadow,
                        className,
                    )}
                >
                    <div className="flex flex-col p-1">{children}</div>
                </DropdownMenu.Content>
            </DropdownMenu.Portal>
        </DropdownMenu.Root>
    );
}

function Item({
    icon,
    label,
    variant = 'default',
    disabled = false,
    onClick,
    className: classNameProp,
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
                : 'font-medium text-delete data-[highlighted]:bg-neutral-bg'
            : disabled
              ? 'cursor-not-allowed text-ink-faint'
              : 'text-ink-soft data-[highlighted]:bg-neutral-bg';

    return (
        <DropdownMenu.Item
            disabled={disabled}
            onSelect={onClick}
            className={cn(itemBase, variantStyles, classNameProp)}
        >
            {children ?? (
                <>
                    {icon}
                    {label}
                </>
            )}
        </DropdownMenu.Item>
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
        <DropdownMenu.Sub>
            <DropdownMenu.SubTrigger
                className={cn(
                    itemBase,
                    'justify-between text-ink-soft data-[highlighted]:bg-neutral-bg',
                )}
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
            </DropdownMenu.SubTrigger>
            <DropdownMenu.Portal>
                <DropdownMenu.SubContent
                    sideOffset={4}
                    className={cn(
                        'z-50 rounded-lg bg-surface-card',
                        menuShadow,
                        width,
                    )}
                >
                    <div className="flex flex-col p-1">{children}</div>
                </DropdownMenu.SubContent>
            </DropdownMenu.Portal>
        </DropdownMenu.Sub>
    );
}

function Separator() {
    return <DropdownMenu.Separator className="mx-2 my-1 h-px bg-border" />;
}

const ContextMenu = Object.assign(ContextMenuRoot, {
    Item,
    Submenu,
    Separator,
});

export default ContextMenu;

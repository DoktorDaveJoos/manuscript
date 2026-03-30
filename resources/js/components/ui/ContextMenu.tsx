import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { ChevronRight } from 'lucide-react';
import { type ReactNode, useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import {
    menuContentBase,
    menuItemBase,
    menuItemVariants,
    menuSeparatorBase,
    menuShadow,
} from './menu-primitives';

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
        <DropdownMenu.Root open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DropdownMenu.Trigger asChild>
                <div
                    ref={triggerRef}
                    className="invisible fixed h-0 w-0"
                    style={{ left: position.x, top: position.y }}
                />
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content
                    side="bottom"
                    align="start"
                    className={cn(
                        menuContentBase,
                        'w-[200px]',
                        menuShadow,
                        className,
                    )}
                >
                    {children}
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
    return (
        <DropdownMenu.Item
            disabled={disabled}
            onSelect={onClick}
            className={cn(
                menuItemBase,
                menuItemVariants[variant],
                disabled && 'cursor-not-allowed text-ink-faint',
                className,
            )}
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
                className={cn(menuItemBase, 'justify-between', menuItemVariants.default)}
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
                    className={cn(
                        menuContentBase,
                        menuShadow,
                        width,
                    )}
                >
                    {children}
                </DropdownMenu.SubContent>
            </DropdownMenu.Portal>
        </DropdownMenu.Sub>
    );
}

function Separator() {
    return <DropdownMenu.Separator className={menuSeparatorBase} />;
}

const ContextMenu = Object.assign(ContextMenuRoot, {
    Item,
    Submenu,
    Separator,
});

export default ContextMenu;

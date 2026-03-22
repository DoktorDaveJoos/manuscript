import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu';
import { ChevronRight } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

const menuShadow = 'shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]';
const itemBase =
    'flex w-full items-center gap-2.5 rounded-[5px] px-3 py-2 text-left text-[13px] leading-[18px] transition-colors';

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
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                onClose();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [onClose]);

    return (
        <div
            ref={ref}
            className={cn(
                'fixed z-50 w-[200px] rounded-lg bg-surface-card',
                menuShadow,
                className,
            )}
            style={{ left: position.x, top: position.y }}
        >
            <div className="flex flex-col p-1">{children}</div>
        </div>
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
                : 'font-medium text-delete hover:bg-neutral-bg'
            : disabled
              ? 'cursor-not-allowed text-ink-faint'
              : 'text-ink-soft hover:bg-neutral-bg';

    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={cn(itemBase, variantStyles, className)}
        >
            {children ?? (
                <>
                    {icon}
                    {label}
                </>
            )}
        </button>
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
    const [open, setOpen] = useState(false);

    return (
        <div
            className="relative"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
        >
            <button
                type="button"
                className={cn(itemBase, 'justify-between text-ink-soft hover:bg-neutral-bg')}
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
            </button>
            {open && (
                <div
                    className={cn(
                        'absolute top-0 left-full ml-1 rounded-lg bg-surface-card',
                        menuShadow,
                        width,
                    )}
                >
                    <div className="flex flex-col p-1">{children}</div>
                </div>
            )}
        </div>
    );
}

function Separator() {
    return <div className="mx-2 my-1 h-px bg-border" />;
}

const DropdownMenu = DropdownMenuPrimitive.Root;
const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;
const DropdownMenuContent = DropdownMenuPrimitive.Content;
const DropdownMenuItem = DropdownMenuPrimitive.Item;
const DropdownMenuSeparator = DropdownMenuPrimitive.Separator;
const DropdownMenuSub = DropdownMenuPrimitive.Sub;
const DropdownMenuSubTrigger = DropdownMenuPrimitive.SubTrigger;
const DropdownMenuSubContent = DropdownMenuPrimitive.SubContent;

const ContextMenu = Object.assign(ContextMenuRoot, {
    Item,
    Submenu,
    Separator,
});

export {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubTrigger,
    DropdownMenuSubContent,
};

export default ContextMenu;

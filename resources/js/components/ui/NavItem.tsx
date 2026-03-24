import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const ACTIVE_STYLES = {
    default: 'bg-neutral-bg font-medium text-ink',
    inverted: 'bg-ink font-medium text-surface',
} as const;

type ActiveVariant = keyof typeof ACTIVE_STYLES;

export default function NavItem({
    label,
    icon,
    href,
    isActive,
    disabled,
    onClick,
    suffix,
    activeVariant = 'default',
    iconOnly = false,
}: {
    label: string;
    icon?: React.ReactNode;
    href?: string;
    isActive?: boolean;
    disabled?: boolean;
    onClick?: () => void;
    suffix?: React.ReactNode;
    activeVariant?: ActiveVariant;
    iconOnly?: boolean;
}) {
    const stateClasses = isActive
        ? ACTIVE_STYLES[activeVariant]
        : disabled
          ? 'cursor-default text-ink-faint'
          : 'text-ink-muted hover:bg-neutral-bg hover:text-ink';

    const classes = cn(
        'flex items-center rounded-md transition-colors',
        iconOnly
            ? 'justify-center size-8'
            : 'gap-2.5 px-2.5 py-[7px] text-[13px]',
        stateClasses,
    );

    const content = iconOnly ? (
        <>{icon}</>
    ) : (
        <>
            {icon}
            {label}
            {suffix}
        </>
    );

    const titleAttr = iconOnly ? label : undefined;

    if (onClick) {
        return (
            <button type="button" onClick={onClick} title={titleAttr} className={cn(classes, !iconOnly && 'w-full text-left')}>
                {content}
            </button>
        );
    }

    if (disabled || !href) {
        return <span title={titleAttr} className={classes}>{content}</span>;
    }

    return (
        <Link href={href} title={titleAttr} className={classes}>
            {content}
        </Link>
    );
}

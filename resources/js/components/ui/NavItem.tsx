import { Link } from '@inertiajs/react';

const ACTIVE_STYLES = {
    default: 'bg-neutral-bg font-medium text-ink',
    inverted: 'bg-ink font-medium text-white',
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
}: {
    label: string;
    icon?: React.ReactNode;
    href?: string;
    isActive?: boolean;
    disabled?: boolean;
    onClick?: () => void;
    suffix?: React.ReactNode;
    activeVariant?: ActiveVariant;
}) {
    const classes = `flex items-center gap-2.5 rounded-md px-2.5 py-[7px] text-[13px] transition-colors ${
        isActive
            ? ACTIVE_STYLES[activeVariant]
            : disabled
              ? 'cursor-default text-ink-faint'
              : 'text-[#8A8A8A] hover:bg-neutral-bg hover:text-ink'
    }`;

    const content = (
        <>
            {icon}
            {label}
            {suffix}
        </>
    );

    if (onClick) {
        return (
            <button type="button" onClick={onClick} className={`${classes} w-full text-left`}>
                {content}
            </button>
        );
    }

    if (disabled || !href) {
        return <span className={classes}>{content}</span>;
    }

    return (
        <Link href={href} className={classes}>
            {content}
        </Link>
    );
}

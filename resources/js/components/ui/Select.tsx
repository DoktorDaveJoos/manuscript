import { ChevronDown } from 'lucide-react';
import { forwardRef, type ReactNode, type SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const selectSizeMap = {
    default: {
        select: 'rounded-lg py-2.5 pr-9 pl-3.5 text-[13px]',
        selectWithIcon: 'pl-9',
        chevron: 'h-3.5 w-3.5 right-3.5',
        icon: 'left-3.5 h-4 w-4',
    },
    compact: {
        select: 'rounded-md py-2 pr-7 pl-3 text-[12px]',
        selectWithIcon: 'pl-8',
        chevron: 'h-3 w-3 right-3',
        icon: 'left-3 h-3.5 w-3.5',
    },
    dialog: {
        select: 'rounded-lg py-3 pr-10 pl-4 text-sm',
        selectWithIcon: 'pl-10',
        chevron: 'h-4 w-4 right-4',
        icon: 'left-4 h-4 w-4',
    },
};

const selectBase =
    'w-full cursor-pointer appearance-none border border-border-subtle bg-white text-ink transition-colors hover:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent disabled:cursor-not-allowed disabled:opacity-50 dark:border-border dark:bg-surface-card';

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    variant?: 'default' | 'compact' | 'dialog';
    icon?: ReactNode;
};

const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ variant = 'default', icon, className, children, ...props }, ref) => {
        const v = selectSizeMap[variant];

        return (
            <div className="relative">
                {icon && (
                    <span
                        className={cn(
                            'pointer-events-none absolute top-1/2 -translate-y-1/2 text-ink-faint [&>svg]:h-full [&>svg]:w-full',
                            v.icon,
                        )}
                    >
                        {icon}
                    </span>
                )}
                <select
                    ref={ref}
                    className={cn(
                        selectBase,
                        v.select,
                        icon && v.selectWithIcon,
                        className,
                    )}
                    {...props}
                >
                    {children}
                </select>
                <ChevronDown
                    className={cn(
                        'pointer-events-none absolute top-1/2 -translate-y-1/2 text-ink-faint',
                        v.chevron,
                    )}
                />
            </div>
        );
    },
);

Select.displayName = 'Select';

export default Select;

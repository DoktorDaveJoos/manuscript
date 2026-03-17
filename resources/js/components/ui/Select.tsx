import { cn } from '@/lib/utils';
import { forwardRef, type SelectHTMLAttributes } from 'react';

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    variant?: 'default' | 'dialog';
};

const base =
    'w-full appearance-none rounded-md border border-border bg-surface text-ink focus:outline-none focus:ring-1 focus:ring-accent';

const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ variant = 'default', className, children, ...props }, ref) => {
        const size = variant === 'dialog' ? 'px-4 py-3 text-sm' : 'px-3 py-2 text-[13px]';

        return (
            <select
                ref={ref}
                className={cn(base, size, className)}
                {...props}
            >
                {children}
            </select>
        );
    },
);

Select.displayName = 'Select';

export default Select;

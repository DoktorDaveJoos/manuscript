import { cn } from '@/lib/utils';
import { forwardRef, type InputHTMLAttributes } from 'react';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    variant?: 'default' | 'dialog';
};

const base =
    'w-full rounded-md border border-border bg-surface text-ink placeholder:text-ink-faint focus:outline-none focus:ring-1 focus:ring-accent disabled:opacity-60';

const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ variant = 'default', className, ...props }, ref) => {
        const size = variant === 'dialog' ? 'px-4 py-3 text-sm' : 'px-3 py-2 text-[13px]';

        return (
            <input
                ref={ref}
                className={cn(base, size, className)}
                {...props}
            />
        );
    },
);

Input.displayName = 'Input';

export default Input;

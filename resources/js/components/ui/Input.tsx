import { cva, type VariantProps } from 'class-variance-authority';
import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const inputVariants = cva(
    'w-full rounded-md border border-border bg-surface text-ink placeholder:text-ink-faint focus:outline-none focus:ring-1 focus:ring-accent disabled:opacity-60',
    {
        variants: {
            variant: {
                default: 'px-3 py-2 text-[13px]',
                dialog: 'px-4 py-3 text-sm',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

type InputProps = InputHTMLAttributes<HTMLInputElement> &
    VariantProps<typeof inputVariants>;

const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ variant, className, ...props }, ref) => {
        return (
            <input
                ref={ref}
                className={cn(inputVariants({ variant }), className)}
                {...props}
            />
        );
    },
);

Input.displayName = 'Input';

export default Input;

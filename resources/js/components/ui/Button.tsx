import { cn } from '@/lib/utils';
import { forwardRef, type ButtonHTMLAttributes } from 'react';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'accent';
    size?: 'sm' | 'default' | 'lg';
};

const base =
    'inline-flex items-center justify-center rounded-md font-medium transition-colors disabled:opacity-50';

const variants: Record<NonNullable<ButtonProps['variant']>, string> = {
    primary: 'bg-ink text-surface hover:bg-ink/90',
    secondary: 'border border-border text-ink-muted hover:bg-neutral-bg',
    ghost: 'text-ink-muted hover:text-ink',
    danger: 'bg-delete text-surface hover:bg-delete/90',
    accent: 'bg-accent text-surface hover:bg-accent/90',
};

const sizes: Record<NonNullable<ButtonProps['size']>, string> = {
    sm: 'px-3 py-1.5 text-[12px]',
    default: 'px-4 py-2 text-[13px]',
    lg: 'px-6 py-2.5 text-sm',
};

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ variant = 'primary', size = 'default', className, ...props }, ref) => {
        return (
            <button
                ref={ref}
                className={cn(base, variants[variant], sizes[size], className)}
                {...props}
            />
        );
    },
);

Button.displayName = 'Button';

export default Button;

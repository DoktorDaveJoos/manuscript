import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import React, { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center rounded-md font-medium transition-colors disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-ink text-surface hover:bg-ink/90',
                primary: 'bg-ink text-surface hover:bg-ink/90',
                secondary: 'border border-border text-ink-muted hover:bg-neutral-bg',
                ghost: 'text-ink-muted hover:text-ink',
                destructive: 'bg-delete text-surface hover:bg-delete/90',
                danger: 'bg-delete text-surface hover:bg-delete/90',
                accent: 'bg-accent text-surface hover:bg-accent/90',
                outline: 'border border-border bg-transparent text-ink hover:bg-neutral-bg',
                link: 'text-accent underline-offset-4 hover:underline',
            },
            size: {
                default: 'px-4 py-2 text-[13px]',
                sm: 'px-3 py-1.5 text-[12px]',
                lg: 'px-6 py-2.5 text-sm',
                icon: 'h-9 w-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    };

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        if (asChild) {
            return (
                <Slot
                    ref={ref}
                    className={cn(buttonVariants({ variant, size, className }))}
                    {...(props as React.ComponentPropsWithoutRef<typeof Slot>)}
                />
            );
        }

        return (
            <button
                ref={ref}
                className={cn(buttonVariants({ variant, size, className }))}
                {...props}
            />
        );
    },
);

Button.displayName = 'Button';

export { Button, buttonVariants };
export default Button;

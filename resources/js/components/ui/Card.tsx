import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Card({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'rounded-xl border border-border-light bg-surface-card',
                className,
            )}
            {...props}
        />
    );
}

function CardHeader({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('flex flex-col gap-1.5 px-6 pt-6', className)}
            {...props}
        />
    );
}

function CardTitle({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('text-sm font-medium text-ink', className)}
            {...props}
        />
    );
}

function CardDescription({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('text-[13px] text-ink-muted', className)}
            {...props}
        />
    );
}

function CardContent({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('p-6', className)} {...props} />;
}

function CardFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('flex items-center px-6 pb-6', className)}
            {...props}
        />
    );
}

export { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter };

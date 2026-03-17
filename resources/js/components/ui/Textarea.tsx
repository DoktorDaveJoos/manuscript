import { cn } from '@/lib/utils';
import { forwardRef, type TextareaHTMLAttributes } from 'react';

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
    variant?: 'default' | 'dialog';
};

const base =
    'w-full rounded-md border border-border bg-surface text-ink placeholder:text-ink-faint focus:outline-none focus:ring-1 focus:ring-accent resize-none';

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ variant = 'default', className, ...props }, ref) => {
        const size = variant === 'dialog' ? 'px-4 py-3 text-sm' : 'px-3 py-2 text-[13px]';

        return (
            <textarea
                ref={ref}
                className={cn(base, size, className)}
                {...props}
            />
        );
    },
);

Textarea.displayName = 'Textarea';

export default Textarea;

import { cva, type VariantProps } from 'class-variance-authority';
import { forwardRef, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const textareaVariants = cva(
    'w-full rounded-md border border-border bg-surface text-ink placeholder:text-ink-faint focus:outline-none focus:ring-1 focus:ring-accent resize-none disabled:opacity-60',
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

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> &
    VariantProps<typeof textareaVariants>;

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ variant, className, ...props }, ref) => {
        return (
            <textarea
                ref={ref}
                className={cn(textareaVariants({ variant }), className)}
                {...props}
            />
        );
    },
);

Textarea.displayName = 'Textarea';

export default Textarea;

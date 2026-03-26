import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function RadioGroup({
    className,
    ...props
}: ComponentProps<typeof RadioGroupPrimitive.Root>) {
    return (
        <RadioGroupPrimitive.Root
            className={cn('flex flex-col gap-1', className)}
            {...props}
        />
    );
}

function RadioGroupItem({
    className,
    ...props
}: ComponentProps<typeof RadioGroupPrimitive.Item>) {
    return (
        <RadioGroupPrimitive.Item
            className={cn(
                'size-[18px] shrink-0 rounded-full border-2 border-border transition-colors',
                'data-[state=checked]:border-ink',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ink/40',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <RadioGroupPrimitive.Indicator className="flex items-center justify-center">
                <span className="size-[10px] rounded-full bg-ink" />
            </RadioGroupPrimitive.Indicator>
        </RadioGroupPrimitive.Item>
    );
}

export { RadioGroup, RadioGroupItem };

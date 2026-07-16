import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type CheckboxProps = Omit<
    ComponentProps<typeof CheckboxPrimitive.Root>,
    'checked' | 'onCheckedChange'
> & {
    checked: boolean;
    onChange: (checked: boolean) => void;
};

export default function Checkbox({
    checked,
    onChange,
    className,
    ...props
}: CheckboxProps) {
    return (
        <CheckboxPrimitive.Root
            checked={checked}
            onCheckedChange={(nextChecked) => {
                if (nextChecked !== 'indeterminate') {
                    onChange(nextChecked);
                }
            }}
            className={cn(
                'flex size-3.5 shrink-0 items-center justify-center rounded',
                checked ? 'bg-ink' : 'border border-border',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator>
                <Check className="size-3 text-surface" strokeWidth={3} />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

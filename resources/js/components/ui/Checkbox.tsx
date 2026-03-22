import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type CheckboxProps = {
    checked: boolean;
    onChange: () => void;
    className?: string;
};

export default function Checkbox({ checked, onChange, className }: CheckboxProps) {
    return (
        <CheckboxPrimitive.Root
            checked={checked}
            onCheckedChange={onChange}
            className={cn(
                'flex h-[14px] w-[14px] shrink-0 items-center justify-center rounded-[3px]',
                checked ? 'bg-ink' : 'border border-border',
                className,
            )}
        >
            <CheckboxPrimitive.Indicator>
                <Check className="h-[10px] w-[10px] text-surface" strokeWidth={3} />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export { Checkbox };

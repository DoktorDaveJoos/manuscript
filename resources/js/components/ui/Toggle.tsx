import * as SwitchPrimitive from '@radix-ui/react-switch';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type ToggleProps = Omit<
    ComponentProps<typeof SwitchPrimitive.Root>,
    'checked' | 'onCheckedChange'
> & {
    checked: boolean;
    onChange: (checked: boolean) => void;
};

function Toggle({ checked, onChange, className, ...props }: ToggleProps) {
    return (
        <SwitchPrimitive.Root
            checked={checked}
            onCheckedChange={onChange}
            className={cn(
                'relative inline-flex h-[20px] w-[34px] shrink-0 items-center rounded-full transition-colors',
                checked ? 'bg-ink' : 'bg-neutral-bg',
                className,
            )}
            {...props}
        >
            <SwitchPrimitive.Thumb
                className={cn(
                    'inline-block size-3.5 rounded-full bg-white shadow-sm transition-transform dark:bg-surface',
                    checked ? 'translate-x-[17px]' : 'translate-x-[3px]',
                )}
            />
        </SwitchPrimitive.Root>
    );
}

export default Toggle;

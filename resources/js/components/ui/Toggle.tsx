import * as SwitchPrimitive from '@radix-ui/react-switch';
import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

type ToggleProps = {
    checked: boolean;
    onChange: () => void;
    className?: string;
    disabled?: boolean;
};

const Switch = forwardRef<HTMLButtonElement, ToggleProps>(
    ({ checked, onChange, className, disabled }, ref) => {
        return (
            <SwitchPrimitive.Root
                ref={ref}
                checked={checked}
                onCheckedChange={onChange}
                disabled={disabled}
                className={cn(
                    'relative inline-flex h-[20px] w-[34px] shrink-0 items-center rounded-full transition-colors',
                    checked ? 'bg-ink' : 'bg-[#E8E8E8] dark:bg-[#3d3a35]',
                    disabled && 'opacity-50',
                    className,
                )}
            >
                <SwitchPrimitive.Thumb
                    className={cn(
                        'inline-block h-[14px] w-[14px] rounded-full bg-white shadow-sm transition-transform',
                        checked ? 'translate-x-[17px]' : 'translate-x-[3px]',
                    )}
                />
            </SwitchPrimitive.Root>
        );
    },
);

Switch.displayName = 'Switch';

const Toggle = Switch;

export { Switch };
export default Toggle;

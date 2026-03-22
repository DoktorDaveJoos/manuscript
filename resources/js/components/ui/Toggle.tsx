import * as SwitchPrimitive from '@radix-ui/react-switch';
import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

type ToggleProps = {
    checked: boolean;
    onChange: () => void;
};

const Switch = forwardRef<HTMLButtonElement, ToggleProps>(
    ({ checked, onChange }, ref) => {
        return (
            <SwitchPrimitive.Root
                ref={ref}
                checked={checked}
                onCheckedChange={onChange}
                className={cn(
                    'relative inline-flex h-[20px] w-[34px] shrink-0 items-center rounded-full transition-colors',
                    checked ? 'bg-ink' : 'bg-[#E8E8E8] dark:bg-[#3d3a35]',
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

function Toggle({ checked, onChange }: ToggleProps) {
    return <Switch checked={checked} onChange={onChange} />;
}

export { Switch };
export default Toggle;

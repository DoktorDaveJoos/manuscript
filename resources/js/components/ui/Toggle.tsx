import * as SwitchPrimitive from '@radix-ui/react-switch';
import { cn } from '@/lib/utils';

function Toggle({ checked, onChange }: { checked: boolean; onChange: () => void }) {
    return (
        <SwitchPrimitive.Root
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
}

export default Toggle;

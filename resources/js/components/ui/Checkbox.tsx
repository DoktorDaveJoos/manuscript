import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type CheckboxProps = {
    checked: boolean;
    onChange: () => void;
    className?: string;
};

export default function Checkbox({ checked, onChange, className }: CheckboxProps) {
    return (
        <button
            type="button"
            onClick={onChange}
            className={cn(
                'flex h-[14px] w-[14px] shrink-0 items-center justify-center rounded-[3px]',
                checked ? 'bg-ink' : 'border border-[#D0CFCD] dark:border-ink-faint',
                className,
            )}
        >
            {checked && <Check className="h-[10px] w-[10px] text-surface" strokeWidth={3} />}
        </button>
    );
}

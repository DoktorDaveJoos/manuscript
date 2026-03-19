import Toggle from '@/components/ui/Toggle';
import { cn } from '@/lib/utils';

type ToggleRowProps = {
    label: string;
    checked: boolean;
    onChange: () => void;
    border?: boolean;
};

export default function ToggleRow({ label, checked, onChange, border = true }: ToggleRowProps) {
    return (
        <div className={cn('flex items-center justify-between py-3', border && 'border-b border-border-subtle')}>
            <span className="text-[13px] text-ink-soft">{label}</span>
            <Toggle checked={checked} onChange={onChange} />
        </div>
    );
}

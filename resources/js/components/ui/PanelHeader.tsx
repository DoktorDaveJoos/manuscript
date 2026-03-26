import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

type PanelHeaderProps = {
    title: string;
    icon?: React.ReactNode;
    onClose?: () => void;
    suffix?: React.ReactNode;
    className?: string;
};

export default function PanelHeader({ title, icon, onClose, suffix, className }: PanelHeaderProps) {
    return (
        <div className={cn('flex h-11 shrink-0 items-center justify-between border-b border-border px-4', className)}>
            <div className="flex items-center gap-2">
                {icon}
                <span className="text-[11px] font-semibold tracking-[0.06em] text-ink uppercase">
                    {title}
                </span>
            </div>
            <div className="flex items-center gap-1.5">
                {suffix}
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex size-6 items-center justify-center rounded text-ink-faint transition-colors hover:text-ink"
                    >
                        <X size={14} />
                    </button>
                )}
            </div>
        </div>
    );
}

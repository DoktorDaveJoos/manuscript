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
        <div className={cn('flex items-center justify-between border-b border-border px-4 py-3', className)}>
            <div className="flex items-center gap-2">
                {icon}
                <span className="text-xs font-medium tracking-[0.08em] text-ink-muted uppercase">
                    {title}
                </span>
            </div>
            <div className="flex items-center gap-2">
                {suffix}
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex size-6 items-center justify-center rounded text-ink-muted transition-colors hover:text-ink-soft"
                    >
                        <X size={14} strokeWidth={2.5} />
                    </button>
                )}
            </div>
        </div>
    );
}

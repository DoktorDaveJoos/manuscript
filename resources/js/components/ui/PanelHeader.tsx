import { X } from 'lucide-react';
import Button from '@/components/ui/Button';
import { cn } from '@/lib/utils';

type PanelHeaderProps = {
    title: string;
    icon?: React.ReactNode;
    onClose?: () => void;
    suffix?: React.ReactNode;
    className?: string;
    closeLabel?: string;
};

export default function PanelHeader({
    title,
    icon,
    onClose,
    suffix,
    className,
    closeLabel = 'Close',
}: PanelHeaderProps) {
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
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={onClose}
                        aria-label={closeLabel}
                        className="size-6 rounded text-ink-faint hover:text-ink"
                    >
                        <X size={14} />
                    </Button>
                )}
            </div>
        </div>
    );
}

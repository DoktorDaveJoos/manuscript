import { cn } from '@/lib/utils';
import { type PropsWithChildren, useEffect } from 'react';

type DrawerProps = PropsWithChildren<{
    onClose: () => void;
    className?: string;
}>;

export default function Drawer({ onClose, className, children }: DrawerProps) {
    useEffect(() => {
        function handleEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50">
            <div
                className="absolute inset-0 bg-black/5"
                onClick={onClose}
            />
            <aside
                className={cn(
                    'absolute top-0 right-0 bottom-0 flex w-[320px] flex-col border-l border-border bg-surface-card shadow-[-4px_0_24px_rgba(0,0,0,0.06)] animate-[slideInRight_150ms_ease-out]',
                    className,
                )}
            >
                {children}
            </aside>
        </div>
    );
}

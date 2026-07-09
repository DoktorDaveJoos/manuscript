import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import { cn } from '@/lib/utils';

export default function NavGroup({
    label,
    storageKey,
    defaultOpen = false,
    containsActive = false,
    testId,
    children,
}: {
    label: string;
    storageKey: string;
    defaultOpen?: boolean;
    containsActive?: boolean;
    testId: string;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState<boolean>(() => {
        const stored = localStorage.getItem(storageKey);
        if (stored !== null) {
            return stored === 'true';
        }
        return defaultOpen;
    });

    useEffect(() => {
        if (containsActive) {
            setOpen(true);
        }
    }, [containsActive]);

    const handleOpenChange = (next: boolean) => {
        setOpen(next);
        localStorage.setItem(storageKey, String(next));
    };

    return (
        <Collapsible open={open} onOpenChange={handleOpenChange}>
            <CollapsibleTrigger
                data-testid={testId}
                className="flex w-full items-center gap-2.5 rounded-md px-2.5 py-[7px] text-[13px] text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
            >
                <ChevronRight
                    size={14}
                    className={cn(
                        'shrink-0 text-ink-faint transition-transform',
                        open && 'rotate-90',
                    )}
                />
                {label}
            </CollapsibleTrigger>
            {open && (
                <CollapsibleContent
                    data-testid={`${testId}-content`}
                    className="flex flex-col gap-0.5 pl-4"
                >
                    {children}
                </CollapsibleContent>
            )}
        </Collapsible>
    );
}

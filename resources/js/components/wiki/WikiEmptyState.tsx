import { ListBullets } from '@phosphor-icons/react';

export default function WikiEmptyState() {
    return (
        <div className="flex flex-1 flex-col items-center justify-center text-center">
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-bg">
                <ListBullets size={20} weight="regular" className="text-ink-faint" />
            </div>
            <p className="text-[13px] text-ink-muted">Select an item to view details</p>
        </div>
    );
}

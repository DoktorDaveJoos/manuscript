import { index as trashIndex, restore as trashRestore, empty as trashEmpty } from '@/actions/App/Http/Controllers/TrashController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { TrashItem } from '@/types/models';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const typeIcon: Record<TrashItem['type'], React.ReactNode> = {
    storyline: (
        <svg width="8" height="8" viewBox="0 0 8 8" fill="none" className="shrink-0">
            <circle cx="4" cy="4" r="3" stroke="currentColor" strokeWidth="1.5" />
        </svg>
    ),
    chapter: (
        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" className="shrink-0">
            <path d="M4 2h8a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" strokeWidth="1.5" />
            <path d="M6 5h4M6 8h4" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" />
        </svg>
    ),
    scene: (
        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" className="shrink-0">
            <path d="M3 4h10M3 8h7M3 12h9" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
    ),
};

export default function TrashBin({ bookId }: { bookId: number }) {
    const [isOpen, setIsOpen] = useState(false);
    const [items, setItems] = useState<TrashItem[]>([]);
    const [loading, setLoading] = useState(false);
    const prevOpen = useRef(false);

    const fetchItems = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(trashIndex.url(bookId), {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                setItems(await res.json());
            }
        } finally {
            setLoading(false);
        }
    }, [bookId]);

    useEffect(() => {
        if (isOpen && !prevOpen.current) {
            fetchItems();
        }
        prevOpen.current = isOpen;
    }, [isOpen, fetchItems]);

    const handleRestore = async (item: TrashItem) => {
        const res = await fetch(trashRestore.url(bookId), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            body: JSON.stringify({ type: item.type, id: item.id }),
        });
        if (res.ok) {
            setItems((prev) => prev.filter((i) => !(i.id === item.id && i.type === item.type)));
            router.reload({ only: ['book'] });
        }
    };

    const handleEmpty = async () => {
        if (!confirm('Permanently delete all trashed items? This cannot be undone.')) return;
        const res = await fetch(trashEmpty.url(bookId), {
            method: 'DELETE',
            headers: jsonFetchHeaders(),
        });
        if (res.ok) {
            setItems([]);
        }
    };

    return (
        <div className="border-t border-border-subtle">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center gap-2 px-5 py-2.5 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint transition-colors hover:text-ink-muted"
            >
                <span className={`flex shrink-0 items-center transition-transform ${isOpen ? 'rotate-90' : ''}`}>
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor">
                        <path d="M2 1l4 3-4 3V1z" />
                    </svg>
                </span>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" className="shrink-0">
                    <path d="M3 5h10l-.7 8.1a1 1 0 01-1 .9H4.7a1 1 0 01-1-.9L3 5z" stroke="currentColor" strokeWidth="1.3" />
                    <path d="M2 5h12M6 2h4" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
                </svg>
                Trash
                {items.length > 0 && (
                    <span className="ml-auto rounded-full bg-ink/[0.06] px-1.5 py-px text-[10px] font-medium tabular-nums text-ink-faint">
                        {items.length}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="flex flex-col pb-2">
                    {loading && items.length === 0 && (
                        <div className="px-5 py-2 text-[11px] text-ink-faint">Loading…</div>
                    )}
                    {!loading && items.length === 0 && (
                        <div className="px-5 py-2 text-[11px] text-ink-faint">Trash is empty</div>
                    )}
                    {items.map((item) => (
                        <div
                            key={`${item.type}-${item.id}`}
                            className="group flex items-center gap-2 px-5 py-1.5 text-[13px] text-ink-muted"
                        >
                            <span className="text-ink-faint">{typeIcon[item.type]}</span>
                            <span className="min-w-0 flex-1 truncate">{item.name}</span>
                            <button
                                type="button"
                                onClick={() => handleRestore(item)}
                                className="shrink-0 text-[11px] text-ink-faint opacity-0 transition-opacity hover:text-ink group-hover:opacity-100"
                            >
                                Restore
                            </button>
                        </div>
                    ))}
                    {!loading && items.length > 0 && (
                        <button
                            type="button"
                            onClick={handleEmpty}
                            className="mx-5 mt-1 text-left text-[11px] text-ink-faint hover:text-delete"
                        >
                            Empty trash
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

import { index as trashIndex, restore as trashRestore, empty as trashEmpty } from '@/actions/App/Http/Controllers/TrashController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { TrashItem } from '@/types/models';
import { router } from '@inertiajs/react';
import { AlignLeft, ChevronRight, Circle, File, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

const typeIcon: Record<TrashItem['type'], React.ReactNode> = {
    storyline: <Circle size={8} className="shrink-0" />,
    chapter: <File size={10} className="shrink-0" />,
    scene: <AlignLeft size={10} className="shrink-0" />,
};

export default function TrashBin({ bookId }: { bookId: number }) {
    const { t } = useTranslation('editor');
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
        if (!confirm(t('trash.confirmEmpty'))) return;
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
                    <ChevronRight size={8} strokeWidth={2.5} />
                </span>
                <Trash2 size={14} className="shrink-0" />
                {t('trash.title')}
                {items.length > 0 && (
                    <span className="ml-auto rounded-full bg-ink/[0.06] px-1.5 py-px text-[10px] font-medium tabular-nums text-ink-faint">
                        {items.length}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="flex flex-col pb-2">
                    {loading && items.length === 0 && (
                        <div className="px-5 py-2 text-[11px] text-ink-faint">{t('trash.loading')}</div>
                    )}
                    {!loading && items.length === 0 && (
                        <div className="px-5 py-2 text-[11px] text-ink-faint">{t('trash.empty')}</div>
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
                                {t('trash.restore')}
                            </button>
                        </div>
                    ))}
                    {!loading && items.length > 0 && (
                        <button
                            type="button"
                            onClick={handleEmpty}
                            className="mx-5 mt-1 text-left text-[11px] text-ink-faint hover:text-delete"
                        >
                            {t('trash.emptyTrash')}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}

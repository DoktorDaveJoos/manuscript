import { router } from '@inertiajs/react';
import { AlignLeft, ChevronRight, Circle, File, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    index as trashIndex,
    restore as trashRestore,
    empty as trashEmpty,
} from '@/actions/App/Http/Controllers/TrashController';
import SectionLabel from '@/components/ui/SectionLabel';
import { jsonFetchHeaders } from '@/lib/utils';
import type { TrashItem } from '@/types/models';

const typeIcon: Record<TrashItem['type'], React.ReactNode> = {
    storyline: <Circle size={12} className="shrink-0" />,
    chapter: <File size={12} className="shrink-0" />,
    scene: <AlignLeft size={12} className="shrink-0" />,
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
            setItems((prev) =>
                prev.filter((i) => !(i.id === item.id && i.type === item.type)),
            );
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

    const handleRestoreAll = async () => {
        const results = await Promise.all(
            items.map((item) =>
                fetch(trashRestore.url(bookId), {
                    method: 'POST',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ type: item.type, id: item.id }),
                }).then((res) => ({ item, ok: res.ok })),
            ),
        );
        const failed = results.filter((r) => !r.ok).map((r) => r.item);
        setItems(failed);
        router.reload({ only: ['book'] });
    };

    return (
        <div className="border-t border-border-light px-3 py-1">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between px-2.5 pt-2.5 pb-1.5 transition-colors hover:text-ink-muted"
            >
                <span className="flex items-center gap-1.5">
                    <Trash2 size={12} className="shrink-0 text-ink-faint" />
                    <SectionLabel variant="section">
                        {t('trash.title')}
                    </SectionLabel>
                </span>
                <span className="flex items-center gap-1.5">
                    {items.length > 0 && (
                        <span className="text-[11px] text-ink-faint tabular-nums">
                            {items.length}
                        </span>
                    )}
                    <span
                        className={`flex shrink-0 items-center text-ink-faint transition-transform ${isOpen ? 'rotate-90' : ''}`}
                    >
                        <ChevronRight size={12} />
                    </span>
                </span>
            </button>

            {isOpen && (
                <div className="flex flex-col pb-2">
                    {loading && items.length === 0 && (
                        <div className="px-2.5 py-1.5 text-[11px] text-ink-faint">
                            {t('trash.loading')}
                        </div>
                    )}
                    {!loading && items.length === 0 && (
                        <div className="px-2.5 py-1.5 text-[11px] text-ink-faint">
                            {t('trash.empty')}
                        </div>
                    )}
                    {items.map((item) => (
                        <div
                            key={`${item.type}-${item.id}`}
                            className="group flex items-center gap-2 rounded-md px-2.5 py-1.5 text-[12px] text-ink-muted"
                        >
                            <span className="text-ink-faint">
                                {typeIcon[item.type]}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                {item.name}
                            </span>
                            <button
                                type="button"
                                onClick={() => handleRestore(item)}
                                className="shrink-0 text-[11px] text-ink-faint opacity-0 transition-opacity group-hover:opacity-100 hover:text-ink"
                            >
                                {t('trash.restore')}
                            </button>
                        </div>
                    ))}
                    {!loading && items.length > 0 && (
                        <div className="flex items-center justify-between px-2.5 pt-2.5 pb-1">
                            <button
                                type="button"
                                onClick={handleRestoreAll}
                                className="text-[11px] text-ink-faint hover:text-ink"
                            >
                                {t('trash.restoreAll')}
                            </button>
                            <button
                                type="button"
                                onClick={handleEmpty}
                                className="text-[11px] text-ink-faint hover:text-delete"
                            >
                                {t('trash.emptyTrash')}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

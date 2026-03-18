import { router } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';

type BookRef = { id: number; title: string };

export default function BookSwitcher({
    currentBook,
    books,
}: {
    currentBook: BookRef;
    books: BookRef[];
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    const switchBook = () => {
        setOpen(false);
        const from = new URLSearchParams(window.location.search).get('from');
        const url = from
            ? settingsIndex.url({ query: { from } })
            : settingsIndex.url();
        router.visit(url);
    };

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-[11px] font-semibold tracking-[0.05em] text-ink-faint uppercase transition-colors hover:bg-neutral-bg"
            >
                <span className="truncate">{currentBook.title}</span>
                <ChevronsUpDown
                    size={12}
                    strokeWidth={2.5}
                    className="shrink-0"
                />
            </button>

            {open && books.length > 1 && (
                <div className="absolute right-0 left-0 z-10 mt-1 rounded-md border border-border bg-surface-card py-1 shadow-sm">
                    {books.map((book) => (
                        <button
                            key={book.id}
                            type="button"
                            onClick={() => switchBook()}
                            className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                        >
                            <Check
                                size={12}
                                strokeWidth={2.5}
                                className={`shrink-0 ${book.id === currentBook.id ? 'opacity-100' : 'opacity-0'}`}
                            />
                            <span className="truncate">{book.title}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

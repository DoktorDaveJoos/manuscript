import { index as bookSettingsIndex } from '@/actions/App/Http/Controllers/BookSettingsController';
import { router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';

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
        function handleClickOutside(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const switchBook = (book: BookRef) => {
        setOpen(false);
        router.visit(bookSettingsIndex.url(book));
    };

    const truncate = (text: string, maxLen: number) =>
        text.length > maxLen ? text.slice(0, maxLen) + '...' : text;

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.05em] text-ink-faint transition-colors hover:bg-[#F5F2EC]"
            >
                <span className="truncate">{truncate(currentBook.title, 20)}</span>
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 16 16"
                    fill="none"
                    className="shrink-0"
                >
                    <path d="M5.5 3.5L8 1L10.5 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    <path d="M5.5 12.5L8 15L10.5 12.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>

            {open && books.length > 1 && (
                <div className="absolute left-0 right-0 z-10 mt-1 rounded-md border border-border bg-white py-1 shadow-sm">
                    {books.map((book) => (
                        <button
                            key={book.id}
                            type="button"
                            onClick={() => switchBook(book)}
                            className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-ink-muted transition-colors hover:bg-[#F5F2EC] hover:text-ink"
                        >
                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 16 16"
                                fill="none"
                                className={`shrink-0 ${book.id === currentBook.id ? 'opacity-100' : 'opacity-0'}`}
                            >
                                <path d="M3 8.5L6.5 12L13 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                            <span>{truncate(book.title, 24)}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

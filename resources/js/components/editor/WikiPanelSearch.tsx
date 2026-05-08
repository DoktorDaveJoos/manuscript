import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import SearchInput from '@/components/ui/SearchInput';
import WikiAvatar from '@/components/wiki/WikiAvatar';
import { cn } from '@/lib/utils';
import { capitalize, kindToTab } from './WikiPanelCard';

export type SearchResult = {
    id: number;
    type: 'character' | 'wiki_entry';
    name: string;
    kind: string;
    entry_type: string | null;
    description: string | null;
    aliases: string[] | null;
};

export default function WikiPanelSearch({
    results,
    onSearch,
    onSelect,
}: {
    results: SearchResult[];
    onSearch: (query: string) => void;
    onSelect: (result: SearchResult) => void;
}) {
    const { t } = useTranslation('wiki-panel');
    const { t: tWiki } = useTranslation('wiki');
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const handleChange = useCallback(
        (value: string) => {
            setQuery(value);
            clearTimeout(debounceRef.current);
            if (value.trim()) {
                debounceRef.current = setTimeout(() => {
                    onSearch(value.trim());
                    setIsOpen(true);
                }, 300);
            } else {
                setIsOpen(false);
            }
        },
        [onSearch],
    );

    const handleSelect = useCallback(
        (result: SearchResult) => {
            onSelect(result);
            setQuery('');
            setIsOpen(false);
        },
        [onSelect],
    );

    // Close popover on outside click
    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    // Cleanup debounce on unmount
    useEffect(() => {
        return () => clearTimeout(debounceRef.current);
    }, []);

    return (
        <div ref={containerRef} className="relative border-b border-border">
            <div className="px-3 py-3">
                <SearchInput
                    value={query}
                    onChange={handleChange}
                    placeholder={t('searchPlaceholder')}
                />
            </div>

            {isOpen && results.length > 0 && (
                <div className="absolute top-full right-3 left-3 z-10 rounded-lg bg-surface-card p-1 shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                    {results.map((result) => (
                        <button
                            key={`${result.type}-${result.id}`}
                            type="button"
                            onClick={() => handleSelect(result)}
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors',
                                'hover:bg-neutral-bg',
                            )}
                        >
                            <WikiAvatar
                                name={result.name}
                                tab={kindToTab(result.kind)}
                                size="sm"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-[13px] font-medium text-ink">
                                    {result.name}
                                </p>
                                <p className="text-[11px] text-ink-faint">
                                    {tWiki(`dropdown.${result.kind}`, {
                                        defaultValue: capitalize(result.kind),
                                    })}
                                    {result.entry_type &&
                                        ` · ${result.entry_type}`}
                                </p>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {isOpen && query && results.length === 0 && (
                <div className="absolute top-full right-3 left-3 z-10 rounded-lg bg-surface-card p-3 text-center text-[13px] text-ink-muted shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                    {t('searchEmpty')}
                </div>
            )}
        </div>
    );
}

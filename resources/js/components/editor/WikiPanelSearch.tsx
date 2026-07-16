import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandItem,
    CommandList,
} from '@/components/ui/Command';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/Popover';
import SearchInput from '@/components/ui/SearchInput';
import WikiAvatar from '@/components/wiki/WikiAvatar';
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

    // Cleanup debounce on unmount
    useEffect(() => {
        return () => clearTimeout(debounceRef.current);
    }, []);

    return (
        <div className="border-b border-border">
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverAnchor asChild>
                    <div className="px-3 py-3">
                        <SearchInput
                            value={query}
                            onChange={handleChange}
                            placeholder={t('searchPlaceholder')}
                        />
                    </div>
                </PopoverAnchor>
                <PopoverContent sideOffset={0}>
                    <Command shouldFilter={false}>
                        <CommandList>
                            <CommandEmpty>{t('searchEmpty')}</CommandEmpty>
                            <CommandGroup>
                                {results.map((result) => (
                                    <CommandItem
                                        key={`${result.type}-${result.id}`}
                                        value={`${result.name} ${result.kind}`}
                                        onSelect={() => handleSelect(result)}
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
                                                {tWiki(
                                                    `dropdown.${result.kind}`,
                                                    {
                                                        defaultValue:
                                                            capitalize(
                                                                result.kind,
                                                            ),
                                                    },
                                                )}
                                                {result.entry_type &&
                                                    ` · ${result.entry_type}`}
                                            </p>
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}

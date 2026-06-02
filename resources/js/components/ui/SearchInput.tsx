import { Search, X } from 'lucide-react';
import { forwardRef } from 'react';
import { cn } from '@/lib/utils';

export type SearchInputProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    ariaLabel?: string;
    disabled?: boolean;
    className?: string;
    onClear?: () => void;
};

/**
 * Shared search input: rounded surface with a leading magnifier and a
 * trailing clear button while there's text. Use everywhere we ask the user
 * to filter a list (wiki page, wiki side panel, future search affordances).
 */
const SearchInput = forwardRef<HTMLInputElement, SearchInputProps>(
    function SearchInput(
        {
            value,
            onChange,
            placeholder,
            ariaLabel,
            disabled = false,
            className,
            onClear,
        },
        ref,
    ) {
        const handleClear = () => {
            onChange('');
            onClear?.();
        };

        return (
            <div
                className={cn(
                    'flex items-center gap-2 rounded-lg bg-surface-card px-2.5 py-1.5 ring-1 ring-transparent focus-within:ring-border',
                    className,
                )}
            >
                <Search className="size-3.5 shrink-0 text-ink-faint" />
                <input
                    ref={ref}
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    aria-label={ariaLabel ?? placeholder}
                    disabled={disabled}
                    className="min-w-0 flex-1 bg-transparent text-[13px] text-ink placeholder:text-ink-faint focus:outline-none disabled:opacity-60"
                />
                {value.length > 0 && (
                    <button
                        type="button"
                        onClick={handleClear}
                        aria-label="Clear search"
                        className="shrink-0 rounded p-0.5 text-ink-muted hover:text-ink"
                    >
                        <X className="size-3" />
                    </button>
                )}
            </div>
        );
    },
);

export default SearchInput;

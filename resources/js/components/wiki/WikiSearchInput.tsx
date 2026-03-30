import { Search, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function WikiSearchInput({
    query,
    onChange,
}: {
    query: string;
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation('wiki');

    return (
        <div className="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-transparent focus-within:ring-border dark:bg-surface-card">
            <Search size={14} className="shrink-0 text-ink-faint" />
            <input
                type="text"
                value={query}
                onChange={(e) => onChange(e.target.value)}
                placeholder={t('search.placeholder')}
                className="min-w-0 flex-1 bg-transparent text-[13px] text-ink placeholder:text-ink-faint focus:outline-none"
            />
            {query.length > 0 && (
                <button
                    onClick={() => onChange('')}
                    className="shrink-0 rounded p-0.5 text-ink-muted hover:text-ink"
                >
                    <X size={12} />
                </button>
            )}
        </div>
    );
}

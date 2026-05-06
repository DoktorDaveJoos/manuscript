import { Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function PlotPanelSearch({
    onChange,
}: {
    onChange: (query: string) => void;
}) {
    const { t } = useTranslation('plot-panel');
    const [value, setValue] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const fire = useCallback(
        (next: string) => {
            clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => onChange(next.trim()), 300);
        },
        [onChange],
    );

    const handleChange = useCallback(
        (next: string) => {
            setValue(next);
            fire(next);
        },
        [fire],
    );

    const handleClear = useCallback(() => {
        setValue('');
        clearTimeout(debounceRef.current);
        onChange('');
    }, [onChange]);

    useEffect(() => () => clearTimeout(debounceRef.current), []);

    return (
        <div className="relative border-b border-border">
            <div className="flex items-center gap-2 px-4 py-3">
                <Search size={14} className="shrink-0 text-ink-faint" />
                <input
                    value={value}
                    onChange={(e) => handleChange(e.target.value)}
                    placeholder={t('searchPlaceholder')}
                    className="min-w-0 flex-1 bg-transparent text-[13px] text-ink placeholder:text-ink-faint focus:outline-none"
                />
                {value && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="shrink-0 rounded p-0.5 text-ink-muted hover:text-ink"
                    >
                        <X size={14} />
                    </button>
                )}
            </div>
        </div>
    );
}

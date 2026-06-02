import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import SearchInput from '@/components/ui/SearchInput';

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

    useEffect(() => () => clearTimeout(debounceRef.current), []);

    return (
        <div className="relative border-b border-border">
            <div className="px-3 py-3">
                <SearchInput
                    value={value}
                    onChange={handleChange}
                    placeholder={t('searchPlaceholder')}
                />
            </div>
        </div>
    );
}

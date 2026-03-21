import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

type StatusOption<T extends string> = {
    value: T;
    labelKey: string;
    activeClass: string;
};

type Props<T extends string> = {
    options: StatusOption<T>[];
    value: T;
    onChange: (value: T) => void;
};

export default function StatusPillGroup<T extends string>({
    options,
    value,
    onChange,
}: Props<T>) {
    const { t } = useTranslation('plot');

    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((option) => {
                const isActive = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'rounded-full px-2.5 py-1 text-[11px] font-medium transition-colors',
                            isActive
                                ? option.activeClass
                                : 'bg-neutral-bg text-ink-muted hover:text-ink-soft',
                        )}
                    >
                        {t(option.labelKey)}
                    </button>
                );
            })}
        </div>
    );
}

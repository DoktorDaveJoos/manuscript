import { useTranslation } from 'react-i18next';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/ToggleGroup';
import { cn } from '@/lib/utils';

type StatusOption<T extends string> = {
    value: T;
    labelKey: string;
    activeClass: string;
};

type Props<T extends string> = {
    options: StatusOption<T>[];
    value: T | null;
    onChange: (value: T) => void;
};

export default function StatusPillGroup<T extends string>({
    options,
    value,
    onChange,
}: Props<T>) {
    const { t } = useTranslation('plot');

    return (
        <ToggleGroup
            type="single"
            value={value ?? ''}
            onValueChange={(nextValue) => {
                if (nextValue) {
                    onChange(nextValue as T);
                }
            }}
            className="gap-1.5"
        >
            {options.map((option) => {
                const isActive = option.value === value;

                return (
                    <ToggleGroupItem
                        key={option.value}
                        variant="pill"
                        value={option.value}
                        className={cn(
                            isActive
                                ? option.activeClass
                                : 'bg-neutral-bg text-ink-muted hover:text-ink-soft',
                        )}
                    >
                        {t(option.labelKey)}
                    </ToggleGroupItem>
                );
            })}
        </ToggleGroup>
    );
}

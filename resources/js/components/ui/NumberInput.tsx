import { Minus, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';

type NumberInputProps = {
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    step?: number;
    /** Short unit suffix rendered inside the control, e.g. "mm". */
    unit?: string;
    className?: string;
    'aria-label'?: string;
};

/**
 * Compact numeric stepper: − [value] + with an optional unit suffix. Built on
 * the project's tokens to sit alongside <Select variant="compact">. Free typing
 * passes straight through; the value is clamped to min/max on blur and on the
 * stepper buttons so users can still edit mid-entry.
 */
export default function NumberInput({
    value,
    onChange,
    min,
    max,
    step = 1,
    unit,
    className,
    'aria-label': ariaLabel,
}: NumberInputProps) {
    const clamp = (n: number): number => {
        if (Number.isNaN(n)) {
            return min ?? 0;
        }
        if (min !== undefined && n < min) {
            return min;
        }
        if (max !== undefined && n > max) {
            return max;
        }
        return n;
    };

    const atMin = min !== undefined && value <= min;
    const atMax = max !== undefined && value >= max;

    return (
        <div
            className={cn(
                'inline-flex items-stretch rounded-md border border-border bg-surface-card text-[13px] text-ink transition-colors focus-within:ring-1 focus-within:ring-ink',
                className,
            )}
        >
            <button
                type="button"
                aria-label="Decrease"
                onClick={() => onChange(clamp(value - step))}
                disabled={atMin}
                className="flex w-7 items-center justify-center rounded-l-md text-ink-muted transition-colors hover:text-ink disabled:opacity-40"
            >
                <Minus className="size-3.5" />
            </button>
            <input
                type="number"
                inputMode="decimal"
                aria-label={ariaLabel}
                value={value}
                min={min}
                max={max}
                step={step}
                onChange={(e) => onChange(Number(e.target.value))}
                onBlur={(e) => onChange(clamp(Number(e.target.value)))}
                className="w-10 bg-transparent py-2 text-center tabular-nums focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />
            {unit ? <span className="self-center pr-1 text-ink-faint">{unit}</span> : null}
            <button
                type="button"
                aria-label="Increase"
                onClick={() => onChange(clamp(value + step))}
                disabled={atMax}
                className="flex w-7 items-center justify-center rounded-r-md text-ink-muted transition-colors hover:text-ink disabled:opacity-40"
            >
                <Plus className="size-3.5" />
            </button>
        </div>
    );
}

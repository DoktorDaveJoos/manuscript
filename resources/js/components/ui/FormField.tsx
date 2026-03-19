import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: string;
    error?: string;
    children: React.ReactNode;
    className?: string;
};

export default function FormField({ label, error, children, className }: FormFieldProps) {
    return (
        <fieldset className={cn('flex flex-col gap-1.5', className)}>
            <label className="text-xs leading-4 font-medium tracking-[0.08em] text-ink-muted uppercase">
                {label}
            </label>
            {children}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </fieldset>
    );
}

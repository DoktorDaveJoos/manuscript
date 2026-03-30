import { cn } from '@/lib/utils';

type FormFieldProps = {
    id?: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    className?: string;
    labelClassName?: string;
};

export default function FormField({ id, label, error, children, className, labelClassName }: FormFieldProps) {
    return (
        <fieldset id={id} className={cn('flex flex-col gap-1.5', className)}>
            <label className={cn('text-sm font-medium text-ink', labelClassName)}>
                {label}
            </label>
            {children}
            {error && <span className="text-xs text-danger">{error}</span>}
        </fieldset>
    );
}

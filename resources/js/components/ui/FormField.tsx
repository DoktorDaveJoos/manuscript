import { cn } from '@/lib/utils';

type FormFieldProps = {
    id?: string;
    label: string;
    action?: React.ReactNode;
    error?: string;
    children: React.ReactNode;
    className?: string;
    labelClassName?: string;
};

export default function FormField({ id, label, action, error, children, className, labelClassName }: FormFieldProps) {
    const labelEl = (
        <label className={cn('text-sm font-medium text-ink', labelClassName)}>
            {label}
        </label>
    );

    return (
        <fieldset id={id} className={cn('flex flex-col gap-1.5', className)}>
            {action ? (
                <div className="flex items-center justify-between">
                    {labelEl}
                    {action}
                </div>
            ) : (
                labelEl
            )}
            {children}
            {error && <span className="text-xs text-danger">{error}</span>}
        </fieldset>
    );
}

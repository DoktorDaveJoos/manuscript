import {
    Children,
    cloneElement,
    isValidElement,
    useId,
    type ReactElement,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    id?: string;
    label: string;
    action?: ReactNode;
    error?: string;
    children: ReactNode;
    className?: string;
    labelClassName?: string;
};

type LabelledControlProps = {
    id?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

const DIRECT_CONTROL_NAMES = new Set([
    'GenreSelect',
    'Input',
    'MarkdownTextarea',
    'NumberInput',
    'Select',
    'Textarea',
]);

function isDirectControl(element: ReactElement): boolean {
    if (
        typeof element.type === 'string' &&
        ['input', 'select', 'textarea'].includes(element.type)
    ) {
        return true;
    }

    const type = element.type as {
        displayName?: string;
        name?: string;
    };
    const name = type.displayName ?? type.name;

    return name ? DIRECT_CONTROL_NAMES.has(name) : false;
}

export default function FormField({
    id,
    label,
    action,
    error,
    children,
    className,
    labelClassName,
}: FormFieldProps) {
    const generatedId = useId();
    const controlId = id ?? generatedId;
    const labelId = `${controlId}-label`;
    const errorId = `${controlId}-error`;
    let controlLabelAttached = false;

    const labelledChildren = Children.map(children, (child) => {
        if (
            controlLabelAttached ||
            !isValidElement(child) ||
            !isDirectControl(child)
        ) {
            return child;
        }

        controlLabelAttached = true;
        const control = child as ReactElement<LabelledControlProps>;
        const describedBy = [control.props['aria-describedby'], error ? errorId : null]
            .filter(Boolean)
            .join(' ');

        return cloneElement(control, {
            id: control.props.id ?? controlId,
            'aria-describedby': describedBy || undefined,
            'aria-invalid': control.props['aria-invalid'] ?? Boolean(error),
        });
    });

    const labelEl = (
        <label
            id={labelId}
            htmlFor={controlLabelAttached ? controlId : undefined}
            className={cn('text-sm font-medium text-ink', labelClassName)}
        >
            {label}
        </label>
    );

    return (
        <fieldset
            aria-labelledby={labelId}
            aria-describedby={error ? errorId : undefined}
            className={cn('flex flex-col gap-1.5', className)}
        >
            {action ? (
                <div className="flex items-center justify-between">
                    {labelEl}
                    {action}
                </div>
            ) : (
                labelEl
            )}
            {labelledChildren}
            {error && (
                <span id={errorId} className="text-xs text-delete">
                    {error}
                </span>
            )}
        </fieldset>
    );
}

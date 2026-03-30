import type { PropsWithChildren, ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    icon?: ReactNode;
    actions?: ReactNode;
}

export default function PageHeader({
    title,
    subtitle,
    icon,
    actions,
    children,
}: PropsWithChildren<PageHeaderProps>) {
    return (
        <div className="flex items-start justify-between">
            <div className="flex flex-col gap-1">
                {icon ? (
                    <div className="flex items-center gap-2">
                        {icon}
                        <h1 className="font-serif text-2xl font-semibold tracking-[-0.01em] text-ink">
                            {title}
                        </h1>
                    </div>
                ) : (
                    <h1 className="font-serif text-2xl font-semibold tracking-[-0.01em] text-ink">
                        {title}
                    </h1>
                )}
                {subtitle && (
                    <p className="text-[14px] text-ink-muted">{subtitle}</p>
                )}
                {children}
            </div>
            {actions}
        </div>
    );
}

import { cn } from '@/lib/utils';

type SectionLabelProps = {
    children: React.ReactNode;
    as?: 'span' | 'label';
    variant?: 'default' | 'section';
    className?: string;
};

const variantStyles = {
    default: 'text-ink-muted',
    section: 'text-ink-faint',
} as const;

export default function SectionLabel({ children, as: Tag = 'span', variant = 'default', className }: SectionLabelProps) {
    return (
        <Tag className={cn('text-[11px] font-semibold tracking-[0.08em] uppercase', variantStyles[variant], className)}>
            {children}
        </Tag>
    );
}

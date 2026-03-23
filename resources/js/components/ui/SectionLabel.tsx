import { cn } from '@/lib/utils';

type SectionLabelProps = {
    children: React.ReactNode;
    as?: 'span' | 'label';
    className?: string;
};

export default function SectionLabel({ children, as: Tag = 'span', className }: SectionLabelProps) {
    return (
        <Tag className={cn('text-[11px] font-semibold tracking-[0.08em] text-ink-muted uppercase', className)}>
            {children}
        </Tag>
    );
}

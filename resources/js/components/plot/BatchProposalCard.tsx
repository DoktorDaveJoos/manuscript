import { GitCommitHorizontal } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import { cn } from '@/lib/utils';

export type BatchWriteType =
    | 'character'
    | 'storyline'
    | 'plot_point'
    | 'beat'
    | 'wiki_entry';

export type BatchWrite = {
    type: BatchWriteType;
    data: Record<string, unknown>;
};

export type BatchProposalCardProps = {
    proposalId: string;
    writes: BatchWrite[];
    summary: string;
    onApprove: (proposalId: string) => void;
    onCancel: (proposalId: string) => void;
    /** Dim + disable actions when the proposal is no longer the most recent. */
    disabled?: boolean;
};

const SECTION_ORDER: BatchWriteType[] = [
    'character',
    'storyline',
    'plot_point',
    'beat',
    'wiki_entry',
];

const SECTION_LABEL_KEY: Record<BatchWriteType, string> = {
    character: 'batch.section.characters',
    storyline: 'batch.section.storylines',
    plot_point: 'batch.section.plot_points',
    beat: 'batch.section.beats',
    wiki_entry: 'batch.section.wiki_entries',
};

const MAX_ITEMS_PER_SECTION = 6;

/**
 * Machine-rendered preview of a ProposeBatch tool output, shown inline in the
 * chat. Approve/Cancel route back through sendMessage as "APPROVE:batch:<id>"
 * / "CANCEL:batch:<id>" signal strings the agent recognizes.
 */
export default function BatchProposalCard({
    proposalId,
    writes,
    summary,
    onApprove,
    onCancel,
    disabled = false,
}: BatchProposalCardProps) {
    const { t } = useTranslation('plot-coach');

    const grouped = useMemo(() => groupWrites(writes), [writes]);
    const total = writes.length;

    return (
        <div
            className={cn(
                'rounded-xl border border-border-light bg-surface-card p-4',
                disabled && 'opacity-60',
            )}
            data-testid="batch-proposal-card"
            data-proposal-id={proposalId}
        >
            {/* Header */}
            <div className="flex items-center gap-2">
                <GitCommitHorizontal className="h-3.5 w-3.5 text-accent" />
                <span className="text-[13px] font-medium text-ink">
                    {t('batch.preview.title')}
                </span>
                <span className="ml-auto rounded-full border border-border-light px-2 py-[1px] text-[11px] font-medium text-ink-muted">
                    {t('batch.preview.items', { count: total })}
                </span>
            </div>

            {summary && (
                <p className="mt-2 text-[13px] leading-[1.5] text-ink-muted italic">
                    {summary}
                </p>
            )}

            {/* Body: sections */}
            <div className="mt-3 flex flex-col gap-3">
                {SECTION_ORDER.map((type) => {
                    const items = grouped[type];
                    if (!items || items.length === 0) return null;
                    const visible = items.slice(0, MAX_ITEMS_PER_SECTION);
                    const overflow = items.length - visible.length;
                    return (
                        <Section
                            key={type}
                            label={t(SECTION_LABEL_KEY[type])}
                            items={visible}
                            overflow={overflow}
                            type={type}
                        />
                    );
                })}
            </div>

            {/* Footer actions */}
            <div className="mt-4 flex items-center gap-3 border-t border-border-light pt-3">
                <Button
                    variant="accent"
                    size="sm"
                    onClick={() => onApprove(proposalId)}
                    disabled={disabled}
                    data-testid="batch-approve"
                >
                    {t('batch.preview.approve')}
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onCancel(proposalId)}
                    disabled={disabled}
                    data-testid="batch-cancel"
                >
                    {t('batch.preview.cancel')}
                </Button>
                <span className="ml-auto text-[12px] text-ink-faint">
                    {t('batch.preview.hint')}
                </span>
            </div>
        </div>
    );
}

function groupWrites(
    writes: BatchWrite[],
): Record<BatchWriteType, BatchWrite[]> {
    const base: Record<BatchWriteType, BatchWrite[]> = {
        character: [],
        storyline: [],
        plot_point: [],
        beat: [],
        wiki_entry: [],
    };
    for (const w of writes) {
        if (w && typeof w === 'object' && w.type in base) {
            base[w.type].push(w);
        }
    }
    return base;
}

type SectionProps = {
    label: string;
    items: BatchWrite[];
    overflow: number;
    type: BatchWriteType;
};

function Section({ label, items, overflow, type }: SectionProps) {
    return (
        <div>
            <div className="text-[11px] font-medium tracking-wider text-ink-faint uppercase">
                {label}
            </div>
            <ul className="mt-1 flex flex-col gap-1">
                {items.map((item, i) => (
                    <li
                        key={i}
                        className="flex items-start gap-2 text-[13px] leading-[1.5]"
                    >
                        <span className="mt-[1px] text-accent">+</span>
                        <ItemLine type={type} data={item.data} />
                    </li>
                ))}
                {overflow > 0 && (
                    <li className="pl-5 text-[12px] text-ink-faint">
                        + {overflow} more
                    </li>
                )}
            </ul>
        </div>
    );
}

function ItemLine({
    type,
    data,
}: {
    type: BatchWriteType;
    data: Record<string, unknown>;
}) {
    const name = stringify(data.name ?? data.title ?? '(unnamed)');
    const desc = stringify(data.ai_description ?? data.description ?? '');
    const kind = stringify(data.kind ?? data.type ?? '');

    let label = name;
    if (
        (type === 'storyline' ||
            type === 'wiki_entry' ||
            type === 'plot_point') &&
        kind
    ) {
        label = `[${kind}] ${name}`;
    }

    return (
        <span className="min-w-0 flex-1">
            <span className="font-semibold text-ink">{label}</span>
            {desc && <span className="text-ink-muted"> — {desc}</span>}
        </span>
    );
}

function stringify(v: unknown): string {
    if (v == null) return '';
    if (typeof v === 'string') return v;
    if (typeof v === 'number' || typeof v === 'boolean') return String(v);
    return '';
}

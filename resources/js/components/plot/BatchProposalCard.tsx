import { Check, GitCommitHorizontal, Pencil, Undo2, X } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { md } from '@/lib/markdown';
import { cn } from '@/lib/utils';

export type BatchWriteType =
    | 'book_update'
    | 'session_update'
    | 'character'
    | 'storyline'
    | 'act'
    | 'plot_point'
    | 'beat'
    | 'wiki_entry'
    | 'chapter'
    | 'delete';

export type BatchWrite = {
    type: BatchWriteType;
    data: Record<string, unknown>;
};

export type ProposalState = 'pending' | 'approved' | 'cancelled' | 'reverted';

export type BatchProposalCardProps = {
    proposalId: string;
    writes: BatchWrite[];
    summary: string;
    state: ProposalState;
    onApprove: (proposalId: string) => void;
    onCancel: (proposalId: string) => void;
    onUndo: (proposalId: string) => void;
    /** Dim action buttons when a newer proposal has superseded this one. */
    dimmed?: boolean;
};

const SECTION_ORDER: BatchWriteType[] = [
    'book_update',
    'session_update',
    'character',
    'storyline',
    'act',
    'plot_point',
    'beat',
    'wiki_entry',
    'chapter',
    'delete',
];

const SECTION_LABEL_KEY: Record<BatchWriteType, string> = {
    book_update: 'batch.section.book_update',
    session_update: 'batch.section.session_update',
    character: 'batch.section.characters',
    storyline: 'batch.section.storylines',
    act: 'batch.section.acts',
    plot_point: 'batch.section.plot_points',
    beat: 'batch.section.beats',
    wiki_entry: 'batch.section.wiki_entries',
    chapter: 'batch.section.chapters',
    delete: 'batch.section.removals',
};

const MAX_ITEMS_PER_SECTION = 6;

/**
 * Machine-rendered preview of a ProposeBatch tool output, shown inline in the
 * chat. Action buttons route back through sendMessage as wire signals
 * ("APPROVE:batch:<id>", "CANCEL:batch:<id>", "UNDO:proposal:<id>") the
 * controller recognizes.
 */
export default function BatchProposalCard({
    proposalId,
    writes,
    summary,
    state,
    onApprove,
    onCancel,
    onUndo,
    dimmed = false,
}: BatchProposalCardProps) {
    const { t } = useTranslation('plot-coach');

    const grouped = useMemo(() => groupWrites(writes), [writes]);
    const total = writes.length;

    // Approved/cancelled/reverted cards aren't interactive in the normal sense.
    // Only the approved state has a residual action (Undo).
    const isResolved = state !== 'pending';

    return (
        <Card
            className={cn(
                'p-4',
                dimmed && state === 'pending' && 'opacity-60',
                state === 'cancelled' && 'opacity-70',
                state === 'reverted' && 'opacity-70',
            )}
            data-testid="batch-proposal-card"
            data-proposal-id={proposalId}
            data-proposal-state={state}
        >
            {/* Header */}
            <div className="flex items-center gap-2">
                <GitCommitHorizontal className="size-3.5 text-accent" />
                <span className="text-[13px] font-medium text-ink">
                    {t('batch.preview.title')}
                </span>
                <StateBadge state={state} count={total} />
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

            {/* Footer actions vary by state */}
            <div className="mt-4 flex items-center gap-3 border-t border-border-light pt-3">
                {!isResolved && (
                    <>
                        <Button
                            variant="accent"
                            size="sm"
                            onClick={() => onApprove(proposalId)}
                            disabled={dimmed}
                            data-testid="batch-approve"
                        >
                            {t('batch.preview.approve')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onCancel(proposalId)}
                            disabled={dimmed}
                            data-testid="batch-cancel"
                        >
                            {t('batch.preview.cancel')}
                        </Button>
                        <span className="ml-auto text-xs text-ink-faint">
                            {t('batch.preview.hint')}
                        </span>
                    </>
                )}

                {state === 'approved' && (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onUndo(proposalId)}
                            data-testid="batch-undo"
                        >
                            <Undo2 className="mr-1 size-3.5" />
                            {t('batch.preview.undo')}
                        </Button>
                        <span className="ml-auto text-xs text-ink-faint">
                            {t('batch.preview.undo_hint')}
                        </span>
                    </>
                )}

                {(state === 'cancelled' || state === 'reverted') && (
                    <span className="text-xs text-ink-faint">
                        {state === 'cancelled'
                            ? t('batch.preview.cancelled_hint')
                            : t('batch.preview.reverted_hint')}
                    </span>
                )}
            </div>
        </Card>
    );
}

function StateBadge({ state, count }: { state: ProposalState; count: number }) {
    const { t } = useTranslation('plot-coach');

    if (state === 'pending') {
        return (
            <Badge variant="outline" className="ml-auto">
                {t('batch.preview.items', { count })}
            </Badge>
        );
    }

    if (state === 'approved') {
        return (
            <Badge variant="default" className="ml-auto gap-1">
                <Check className="size-3" />
                {t('batch.preview.state_approved')}
            </Badge>
        );
    }

    if (state === 'cancelled') {
        return (
            <Badge variant="outline" className="ml-auto gap-1">
                <X className="size-3" />
                {t('batch.preview.state_cancelled')}
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="ml-auto gap-1">
            <Undo2 className="size-3" />
            {t('batch.preview.state_reverted')}
        </Badge>
    );
}

function groupWrites(
    writes: BatchWrite[],
): Record<BatchWriteType, BatchWrite[]> {
    const base: Record<BatchWriteType, BatchWrite[]> = {
        book_update: [],
        session_update: [],
        character: [],
        storyline: [],
        act: [],
        plot_point: [],
        beat: [],
        wiki_entry: [],
        chapter: [],
        delete: [],
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
                {items.map((item, i) => {
                    const isUpdate =
                        type !== 'delete' &&
                        type !== 'book_update' &&
                        type !== 'session_update' &&
                        item.data?.id !== undefined &&
                        item.data?.id !== null;
                    return (
                        <li
                            key={i}
                            className="flex items-start gap-2 text-[13px] leading-[1.5]"
                        >
                            <BulletGlyph type={type} isUpdate={isUpdate} />
                            <ItemLine type={type} data={item.data} />
                        </li>
                    );
                })}
                {overflow > 0 && (
                    <li className="pl-5 text-xs text-ink-faint">
                        + {overflow} more
                    </li>
                )}
            </ul>
        </div>
    );
}

function BulletGlyph({
    type,
    isUpdate,
}: {
    type: BatchWriteType;
    isUpdate: boolean;
}) {
    if (type === 'delete') {
        return <span className="mt-[1px] text-delete">−</span>;
    }
    if (isUpdate) {
        return <Pencil className="mt-[3px] size-3 text-accent" />;
    }
    return <span className="mt-[1px] text-accent">+</span>;
}

function ItemLine({
    type,
    data,
}: {
    type: BatchWriteType;
    data: Record<string, unknown>;
}) {
    if (type === 'book_update') {
        return <BookUpdateLine data={data} />;
    }

    if (type === 'session_update') {
        return <SessionUpdateLine data={data} />;
    }

    if (type === 'act') {
        return <ActLine data={data} />;
    }

    if (type === 'chapter') {
        return <ChapterLine data={data} />;
    }

    if (type === 'delete') {
        return <DeleteLine data={data} />;
    }

    const name =
        resolveName(
            data,
            type === 'beat' || type === 'plot_point' ? 'title' : 'name',
        ) ?? '(unnamed)';
    const desc = stringify(data.ai_description ?? data.description ?? '');
    const kind = resolveKind(data, type);

    return (
        <span className="min-w-0 flex-1">
            <span className="inline-flex items-center gap-1.5">
                {kind && (
                    <Badge variant="secondary" className="shrink-0">
                        {kind}
                    </Badge>
                )}
                <span className="font-semibold text-ink">{name}</span>
            </span>
            <DescriptionInline text={desc} />
        </span>
    );
}

function DeleteLine({ data }: { data: Record<string, unknown> }) {
    const target = stringify(data.target ?? '?');
    const name = resolveName(data, 'name');
    const id =
        typeof data.id === 'number'
            ? data.id
            : typeof data.id === 'string' && data.id.trim() !== ''
              ? Number(data.id)
              : null;
    const idLabel = id !== null && Number.isFinite(id) ? `id=${id}` : '(no id)';

    return (
        <span className="inline-flex min-w-0 flex-1 items-center gap-1.5">
            <Badge variant="secondary" className="shrink-0">
                {target}
            </Badge>
            {name ? (
                <span className="font-semibold text-ink">{name}</span>
            ) : (
                <span className="text-ink-muted">{idLabel}</span>
            )}
        </span>
    );
}

function ActLine({ data }: { data: Record<string, unknown> }) {
    const title = resolveName(data, 'title') ?? '(untitled)';
    const desc = stringify(data.description ?? '');
    const number =
        typeof data.number === 'number'
            ? data.number
            : typeof data.number === 'string' && data.number.trim() !== ''
              ? Number(data.number)
              : typeof data._existing_number === 'string' &&
                  data._existing_number.trim() !== ''
                ? Number(data._existing_number)
                : null;
    const label =
        number !== null && Number.isFinite(number)
            ? `Act ${number}: ${title}`
            : title;
    return (
        <span className="min-w-0 flex-1">
            <span className="font-semibold text-ink">{label}</span>
            <DescriptionInline text={desc} />
        </span>
    );
}

function ChapterLine({ data }: { data: Record<string, unknown> }) {
    const title = resolveName(data, 'title') ?? '(untitled)';
    const desc = stringify(data.description ?? '');

    const beatIds = Array.isArray(data.beat_ids) ? data.beat_ids : null;

    return (
        <span className="min-w-0 flex-1">
            <span className="font-semibold text-ink">{title}</span>
            {beatIds && (
                <Badge variant="secondary" className="ml-2">
                    {beatIds.length} beat{beatIds.length === 1 ? '' : 's'}
                </Badge>
            )}
            <DescriptionInline text={desc} />
        </span>
    );
}

/**
 * Pick the agent-supplied name first, then the `_existing_name` hint
 * server-injected by ProposeBatch::enrichWrites for id-only update payloads.
 */
function resolveName(
    data: Record<string, unknown>,
    primary: 'name' | 'title',
): string | null {
    const direct = stringify(data[primary]);
    if (direct.trim() !== '') return direct;
    const existing = stringify(data._existing_name);
    if (existing.trim() !== '') return existing;
    return null;
}

function resolveKind(
    data: Record<string, unknown>,
    type: BatchWriteType,
): string | null {
    if (
        type !== 'wiki_entry' &&
        type !== 'storyline' &&
        type !== 'plot_point'
    ) {
        return null;
    }

    const fieldKey = type === 'wiki_entry' ? 'kind' : 'type';
    const direct = stringify(data[fieldKey]);
    if (direct.trim() !== '') return direct;

    const existingField =
        type === 'wiki_entry' ? '_existing_kind' : '_existing_type';
    const existing = stringify(data[existingField]);
    if (existing.trim() !== '') return existing;

    return null;
}

function DescriptionInline({ text }: { text: string }) {
    const trimmed = text.trim();
    const structured = isStructured(trimmed);
    const html = useMemo(() => {
        if (!trimmed) return '';
        return structured ? md.render(trimmed) : md.renderInline(trimmed);
    }, [trimmed, structured]);

    if (!trimmed) return null;

    if (structured) {
        return (
            <div
                className="ai-chat-markdown mt-1 text-[13px] leading-[1.5] text-ink-muted"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <span className="text-ink-muted">
            {' — '}
            <span dangerouslySetInnerHTML={{ __html: html }} />
        </span>
    );
}

function isStructured(text: string): boolean {
    return /(^|\n)\s*(#{1,6}\s|[-*+]\s|\d+\.\s|>\s)/.test(text);
}

function SessionUpdateLine({ data }: { data: Record<string, unknown> }) {
    const { t } = useTranslation('plot-coach');
    const rows: Array<{ label: string; value: string }> = [];

    if ('stage' in data) {
        const v = stringify(data.stage);
        rows.push({
            label: t('batch.session_update.stage'),
            value: v === '' ? t('batch.book_update.cleared') : v,
        });
    }

    if ('coaching_mode' in data) {
        const v = stringify(data.coaching_mode);
        rows.push({
            label: t('batch.session_update.coaching_mode'),
            value: v === '' ? t('batch.book_update.cleared') : v,
        });
    }

    return (
        <span className="min-w-0 flex-1">
            {rows.map((row, i) => (
                <span key={i} className="block">
                    <span className="text-ink-muted">{row.label}: </span>
                    <span className="font-semibold text-ink">{row.value}</span>
                </span>
            ))}
        </span>
    );
}

function BookUpdateLine({ data }: { data: Record<string, unknown> }) {
    const { t } = useTranslation('plot-coach');
    const rows: Array<{ label: string; value: string }> = [];

    if ('premise' in data) {
        const v = stringify(data.premise);
        rows.push({
            label: t('batch.book_update.premise'),
            value: v === '' ? t('batch.book_update.cleared') : v,
        });
    }

    if ('target_word_count' in data) {
        const raw = data.target_word_count;
        const cleared = raw === null || raw === '' || raw === undefined;
        const num =
            typeof raw === 'number'
                ? raw
                : typeof raw === 'string' && raw.trim() !== ''
                  ? Number(raw)
                  : NaN;
        rows.push({
            label: t('batch.book_update.target_word_count'),
            value: cleared
                ? t('batch.book_update.cleared')
                : Number.isFinite(num)
                  ? t('batch.book_update.word_count', {
                        count: Math.round(num),
                    })
                  : stringify(raw),
        });
    }

    if ('genre' in data) {
        const v = stringify(data.genre);
        rows.push({
            label: t('batch.book_update.genre'),
            value: v === '' ? t('batch.book_update.cleared') : v,
        });
    }

    return (
        <span className="min-w-0 flex-1">
            {rows.map((row, i) => (
                <span key={i} className="block">
                    <span className="text-ink-muted">{row.label}: </span>
                    <span className="font-semibold text-ink">{row.value}</span>
                </span>
            ))}
        </span>
    );
}

function stringify(v: unknown): string {
    if (v == null) return '';
    if (typeof v === 'string') return v;
    if (typeof v === 'number' || typeof v === 'boolean') return String(v);
    return '';
}

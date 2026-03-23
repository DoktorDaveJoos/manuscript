import { router } from '@inertiajs/react';
import { BookOpen, Sparkles, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import type { PlotTemplate } from '@/lib/plot-templates';
import type { Book } from '@/types/models';
import { store as setupStructure } from '@/actions/App/Http/Controllers/PlotSetupController';

// TODO: These decorative act-specific color gradations have no direct token matches.
// Consider defining CSS variables (--color-act-1-bg, etc.) if they need dark mode support.
const ACT_COLORS = [
    { bg: '#FAF3EB', border: '#E8D5BE', label: '#C49A6C' },
    { bg: '#F8EDE2', border: '#D4B89A', label: '#B87333' },
    { bg: '#F3ECE4', border: '#C4B8A8', label: '#8B6F47' },
    { bg: '#F0E8DF', border: '#C8B8A4', label: '#8B6F47' },
    { bg: '#EBE4DB', border: '#BAA996', label: '#6B5A40' },
] as const;

type PlotWizardModalProps = {
    book: Book;
    template: PlotTemplate;
    onClose: () => void;
};

export default function PlotWizardModal({
    book,
    template,
    onClose,
}: PlotWizardModalProps) {
    const { t } = useTranslation('plot');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        function handleEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [onClose]);

    const totalBeats = template.acts.reduce(
        (sum, a) => sum + a.beats.length,
        0,
    );

    const handleCreate = () => {
        setSubmitting(true);
        router.post(
            setupStructure.url({ book: book.id }),
            {
                template: template.key,
                acts: template.acts.map((a) => ({
                    title: a.title,
                    color: a.color,
                    beats: a.beats.map((b) => ({
                        title: b.title,
                        type: b.type,
                    })),
                })),
                chapter_assignments: null,
            },
            {
                onFinish: () => setSubmitting(false),
                onSuccess: () => onClose(),
            },
        );
    };

    const renderActCards = () => {
        const cards = template.acts.map((act, index) => {
            const colors = ACT_COLORS[index % ACT_COLORS.length];
            return (
                <div
                    key={index}
                    className="flex flex-1 flex-col gap-2 rounded-lg p-3.5"
                    style={{
                        backgroundColor: colors.bg,
                        border: `1px solid ${colors.border}`,
                    }}
                >
                    <span
                        className="text-[11px] font-bold tracking-[0.04em]"
                        style={{ color: colors.label }}
                    >
                        {t('wizard.actPrefix')} {index + 1} — {act.title}
                    </span>
                    <span className="text-[11px] leading-[1.7] whitespace-pre-line text-ink-muted">
                        {act.beats.map((b) => `\u25B8 ${b.title}`).join('\n')}
                    </span>
                    <div
                        className="h-px w-full opacity-50"
                        style={{ backgroundColor: colors.border }}
                    />
                    <span
                        className="text-[11px] font-bold tracking-[0.04em]"
                        style={{ color: colors.label }}
                    >
                        {t(`wizard.bookLabel.${template.key}`)}
                    </span>
                    <span className="text-[11px] leading-[1.5] text-ink-soft italic">
                        {t(`wizard.example.${template.key}.act${index + 1}`)}
                    </span>
                </div>
            );
        });

        if (template.acts.length <= 3) {
            return <div className="flex gap-2.5">{cards}</div>;
        }

        return (
            <div className="flex flex-col gap-2.5">
                <div className="flex gap-2.5">{cards.slice(0, 3)}</div>
                <div className="flex gap-2.5">{cards.slice(3)}</div>
            </div>
        );
    };

    return (
        <Dialog
            onClose={onClose}
            width={640}
            className="max-h-[85vh] rounded-2xl p-0 shadow-[0_16px_48px_rgba(0,0,0,0.15),0_4px_12px_rgba(0,0,0,0.05)]"
        >
            {/* Header */}
            <div className="flex items-center justify-between px-8 py-6">
                <div className="flex flex-col gap-1">
                    <span className="text-[11px] font-semibold tracking-[2px] text-ink-faint uppercase">
                        {t(`wizard.typeLabel.${template.key}`)}
                    </span>
                    <h2 className="font-serif text-xl leading-8 text-ink">
                        {template.name}
                    </h2>
                </div>
                <button
                    onClick={onClose}
                    className="flex h-8 w-8 items-center justify-center rounded-md text-ink-muted hover:bg-neutral-bg hover:text-ink"
                >
                    <X size={20} />
                </button>
            </div>

            <div className="border-t border-border" />

            {/* Body */}
            <div className="flex flex-col gap-5 overflow-y-auto px-8 py-5">
                <p className="text-[14px] leading-[1.5] text-ink-muted">
                    {t(`wizard.tagline.${template.key}`)}
                </p>

                <div className="flex w-full items-center gap-2 rounded-md bg-accent-light px-3 py-2">
                    <BookOpen size={14} className="shrink-0 text-accent" />
                    <span className="text-[12px] font-medium text-ink-warm">
                        {t(`wizard.provenIn.${template.key}`)}
                    </span>
                </div>

                <div className="flex flex-col gap-2">
                    <span className="text-[11px] font-semibold tracking-[0.1em] text-ink-faint uppercase">
                        {t('wizard.howTheActsWork')}
                    </span>
                    {renderActCards()}
                </div>

                <div className="h-px bg-border" />

                <div className="flex flex-col gap-1.5">
                    <div className="flex items-center gap-4">
                        <span className="text-[14px] font-semibold text-ink">
                            {t('wizard.actsCount', {
                                count: template.acts.length,
                            })}
                        </span>
                        <span className="text-ink-faint">&middot;</span>
                        <span className="text-[14px] font-semibold text-ink">
                            {t('wizard.plotPointsCount', {
                                count: totalBeats,
                            })}
                        </span>
                    </div>
                    <p className="text-[13px] text-ink-muted">
                        {t('wizard.summary')}
                    </p>
                </div>
            </div>

            <div className="border-t border-border" />

            {/* Footer */}
            <div className="flex items-center justify-end px-8 py-5">
                <Button
                    variant="primary"
                    size="lg"
                    onClick={handleCreate}
                    disabled={submitting}
                    className="gap-2 rounded-lg"
                >
                    <Sparkles size={14} />
                    {t('wizard.createStructure')}
                </Button>
            </div>
        </Dialog>
    );
}

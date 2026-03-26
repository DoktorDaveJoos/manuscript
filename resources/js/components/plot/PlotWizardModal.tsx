import { router } from '@inertiajs/react';
import { BookOpen, Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { store as setupStructure } from '@/actions/App/Http/Controllers/PlotSetupController';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import SectionLabel from '@/components/ui/SectionLabel';
import type { PlotTemplate } from '@/lib/plot-templates';
import type { Book } from '@/types/models';

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

    const renderActCards = () => (
        <div className="grid grid-cols-3 gap-2.5">
            {template.acts.map((act, index) => (
                <div
                    key={index}
                    className="flex flex-col gap-2 rounded-lg border border-border-subtle bg-surface-warm p-3.5"
                >
                    <span className="text-[11px] font-semibold tracking-wide text-accent uppercase">
                        {t('wizard.actPrefix')} {index + 1} — {act.title}
                    </span>
                    <span className="text-[11px] leading-relaxed whitespace-pre-line text-ink-muted">
                        {act.beats.map((b) => `\u25B8 ${b.title}`).join('\n')}
                    </span>
                    <div className="h-px w-full bg-border-subtle" />
                    <span className="text-[11px] font-semibold tracking-wide text-accent uppercase">
                        {t(`wizard.bookLabel.${template.key}`)}
                    </span>
                    <span className="text-[11px] leading-relaxed text-ink-soft italic">
                        {t(`wizard.example.${template.key}.act${index + 1}`)}
                    </span>
                </div>
            ))}
        </div>
    );

    return (
        <Dialog onClose={onClose} width={640} className="max-h-[85vh] p-0">
            {/* Header */}
            <div className="flex items-center justify-between px-8 py-6">
                <div className="flex flex-col gap-1">
                    <SectionLabel variant="section">
                        {t(`wizard.typeLabel.${template.key}`)}
                    </SectionLabel>
                    <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                        {template.name}
                    </h2>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={onClose}
                    aria-label={t('common:close')}
                >
                    <X size={16} />
                </Button>
            </div>

            <div className="border-t border-border" />

            {/* Body */}
            <div className="flex flex-col gap-5 overflow-y-auto px-8 py-5">
                <p className="text-sm leading-relaxed text-ink-muted">
                    {t(`wizard.tagline.${template.key}`)}
                </p>

                <div className="flex w-full items-center gap-2 rounded-md bg-accent-light px-3 py-2">
                    <BookOpen size={14} className="shrink-0 text-accent" />
                    <span className="text-xs font-medium text-ink-warm">
                        {t(`wizard.provenIn.${template.key}`)}
                    </span>
                </div>

                <div className="flex flex-col gap-2">
                    <SectionLabel variant="section">
                        {t('wizard.howTheActsWork')}
                    </SectionLabel>
                    {renderActCards()}
                </div>

                <div className="border-t border-border" />

                <div className="flex flex-col gap-1.5">
                    <div className="flex items-center gap-4">
                        <span className="text-sm font-semibold text-ink">
                            {t('wizard.actsCount', {
                                count: template.acts.length,
                            })}
                        </span>
                        <span className="text-ink-faint">&middot;</span>
                        <span className="text-sm font-semibold text-ink">
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
                    className="gap-2"
                >
                    <Sparkles size={14} />
                    {t('wizard.createStructure')}
                </Button>
            </div>
        </Dialog>
    );
}

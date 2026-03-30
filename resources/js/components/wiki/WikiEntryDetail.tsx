import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import type { WikiEntry } from '@/types/models';
import DescriptionBlock from './DescriptionBlock';
import WikiAvatar from './WikiAvatar';
import type { WikiTab } from './WikiTabBar';

export default function WikiEntryDetail({
    entry,
    tab,
    onEdit,
}: {
    entry: WikiEntry;
    tab: WikiTab;
    onEdit?: () => void;
}) {
    const { t } = useTranslation('wiki');

    const hasMetadata = !!entry.first_appearance_chapter;

    return (
        <div className="flex max-w-3xl flex-col gap-6">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    <WikiAvatar name={entry.name} tab={tab} size="lg" />
                    <div>
                        <h2 className="font-serif text-2xl font-semibold tracking-[-0.01em] text-ink">
                            {entry.name}
                        </h2>
                        {entry.type && (
                            <Badge variant="secondary" className="mt-1.5">
                                {entry.type}
                            </Badge>
                        )}
                    </div>
                </div>
                {onEdit && (
                    <Button variant="secondary" size="sm" onClick={onEdit}>
                        <Pencil size={12} />
                        {t('edit.editButton')}
                    </Button>
                )}
            </div>

            {/* Author Description */}
            {entry.description && (
                <Card className="flex flex-col gap-3 p-6">
                    <SectionLabel>{t('description.author')}</SectionLabel>
                    <DescriptionBlock text={entry.description} />
                </Card>
            )}

            {/* AI Description */}
            {entry.ai_description && (
                <Card className="bg-surface-base flex flex-col gap-3 border-border-subtle p-6">
                    <SectionLabel>{t('description.ai')}</SectionLabel>
                    <DescriptionBlock
                        text={entry.ai_description}
                        className="text-[14px] leading-relaxed text-ink-muted"
                    />
                </Card>
            )}

            {/* Metadata */}
            {hasMetadata && (
                <Card className="flex gap-12 p-6">
                    {entry.first_appearance_chapter && (
                        <div>
                            <SectionLabel className="mb-1">
                                {t('firstAppearance')}
                            </SectionLabel>
                            <p className="text-[13px] text-ink">
                                {t('chapterEntry', {
                                    order: entry.first_appearance_chapter
                                        .reader_order,
                                    title: entry.first_appearance_chapter.title,
                                })}
                            </p>
                        </div>
                    )}
                </Card>
            )}

            {/* Source */}
            <Card className="flex gap-12 p-6">
                <div>
                    <SectionLabel className="mb-1">{t('source')}</SectionLabel>
                    <p className="text-[13px] text-ink">
                        {entry.is_ai_extracted
                            ? t('aiExtracted')
                            : t('edit.manualEntry')}
                    </p>
                </div>
            </Card>

            {/* Appears In */}
            {entry.chapters && entry.chapters.length > 0 && (
                <Card className="flex flex-col gap-3 p-6">
                    <SectionLabel>{t('appearsIn')}</SectionLabel>
                    <div className="flex flex-col">
                        {entry.chapters.map((chapter, index) => (
                            <div
                                key={chapter.id}
                                className={`flex items-center gap-4 py-3 ${index > 0 ? 'border-t border-border-subtle' : ''}`}
                            >
                                <span className="w-44 shrink-0 text-[13px] text-ink">
                                    {chapter.reader_order}. {chapter.title}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}

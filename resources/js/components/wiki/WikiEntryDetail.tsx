import type { WikiEntry } from '@/types/models';
import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
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

    return (
        <div className="flex flex-col gap-8">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    <WikiAvatar name={entry.name} tab={tab} size="lg" />
                    <div>
                        <h2 className="font-serif text-[28px] leading-tight tracking-[-0.01em] text-ink">
                            {entry.name}
                        </h2>
                        {entry.type && (
                            <span className="mt-1.5 inline-block rounded border border-border px-2 py-0.5 text-[12px] text-ink-muted">
                                {entry.type}
                            </span>
                        )}
                    </div>
                </div>
                {onEdit && (
                    <button
                        onClick={onEdit}
                        className="flex items-center gap-1.5 rounded border border-border px-3 py-1.5 text-[12px] font-medium text-ink-soft transition-colors hover:bg-neutral-bg"
                    >
                        <Pencil size={12} />
                        {t('edit.editButton')}
                    </button>
                )}
            </div>

            {/* Description */}
            {entry.description && (
                <div>
                    <h3 className="mb-2 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('description')}
                    </h3>
                    <p className="text-[14px] leading-relaxed text-ink">{entry.description}</p>
                </div>
            )}

            {/* Metadata row */}
            <div className="flex gap-12 border-t border-border-subtle pt-6">
                {entry.first_appearance_chapter && (
                    <div>
                        <h4 className="mb-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {t('firstAppearance')}
                        </h4>
                        <p className="text-[13px] text-ink">
                            {t('chapterEntry', {
                                order: entry.first_appearance_chapter.reader_order,
                                title: entry.first_appearance_chapter.title,
                            })}
                        </p>
                    </div>
                )}
                {entry.is_ai_extracted && (
                    <div>
                        <h4 className="mb-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {t('source')}
                        </h4>
                        <p className="text-[13px] text-ink">{t('aiExtracted')}</p>
                    </div>
                )}
            </div>

            {/* Appears In */}
            {entry.chapters && entry.chapters.length > 0 && (
                <div>
                    <h3 className="mb-3 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('appearsIn')}
                    </h3>
                    <div className="flex flex-col">
                        {entry.chapters.map((chapter) => (
                            <div
                                key={chapter.id}
                                className="flex items-center gap-4 border-t border-border-subtle py-3"
                            >
                                <span className="w-44 shrink-0 text-[13px] text-ink">
                                    {chapter.reader_order}. {chapter.title}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

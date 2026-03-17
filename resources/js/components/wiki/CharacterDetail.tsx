import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Character } from '@/types/models';
import WikiAvatar from './WikiAvatar';

export default function CharacterDetail({ character, onEdit }: { character: Character; onEdit?: () => void }) {
    const { t } = useTranslation('wiki');

    const storylineLabels = character.storylines?.length
        ? character.storylines.map((s) => t('storylineLabel', { name: s })).join(', ')
        : null;

    return (
        <div className="flex flex-col gap-8">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    <WikiAvatar name={character.name} tab="characters" size="lg" />
                    <div>
                        <h2 className="font-serif text-[28px] leading-tight tracking-[-0.01em] text-ink">
                            {character.name}
                        </h2>
                    {character.aliases && character.aliases.length > 0 && (
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            <span className="text-[12px] text-ink-muted">{t('alsoKnownAs')}</span>
                            {character.aliases.map((alias) => (
                                <span
                                    key={alias}
                                    className="rounded border border-border px-2 py-0.5 text-[12px] text-ink-muted"
                                >
                                    {alias}
                                </span>
                            ))}
                        </div>
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
            {character.description && (
                <div>
                    <h3 className="mb-2 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('description')}
                    </h3>
                    <p className="text-[14px] leading-relaxed text-ink">{character.description}</p>
                </div>
            )}

            {/* Metadata row */}
            <div className="flex gap-12 border-t border-border-subtle pt-6">
                {character.first_appearance_chapter && (
                    <div>
                        <h4 className="mb-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {t('firstAppearance')}
                        </h4>
                        <p className="text-[13px] text-ink">
                            {t('chapterEntry', {
                                order: character.first_appearance_chapter.reader_order,
                                title: character.first_appearance_chapter.title,
                            })}
                        </p>
                    </div>
                )}
                {storylineLabels && (
                    <div>
                        <h4 className="mb-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {t('storylinesHeading')}
                        </h4>
                        <p className="text-[13px] text-ink">{storylineLabels}</p>
                    </div>
                )}
                {character.is_ai_extracted && (
                    <div>
                        <h4 className="mb-1 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {t('source')}
                        </h4>
                        <p className="text-[13px] text-ink">{t('aiExtracted')}</p>
                    </div>
                )}
            </div>

            {/* Appears In */}
            {character.chapters && character.chapters.length > 0 && (
                <div>
                    <h3 className="mb-3 text-[11px] font-medium uppercase tracking-[0.08em] text-ink-muted">
                        {t('appearsIn')}
                    </h3>
                    <div className="flex flex-col">
                        {character.chapters.map((chapter) => (
                            <div
                                key={chapter.id}
                                className="flex items-center gap-4 border-t border-border-subtle py-3"
                            >
                                <span className="w-44 shrink-0 text-[13px] text-ink">
                                    {chapter.reader_order}. {chapter.title}
                                </span>
                                <span
                                    className={`shrink-0 rounded px-2 py-0.5 text-[11px] font-medium ${
                                        chapter.pivot.role === 'protagonist'
                                            ? 'bg-accent/10 text-accent'
                                            : chapter.pivot.role === 'supporting'
                                              ? 'bg-neutral-bg text-ink-muted'
                                              : 'bg-neutral-bg text-ink-faint'
                                    }`}
                                >
                                    {t(`role.${chapter.pivot.role}`)}
                                </span>
                                {chapter.pivot.notes && (
                                    <span className="text-[12px] italic text-ink-muted">
                                        {chapter.pivot.notes}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

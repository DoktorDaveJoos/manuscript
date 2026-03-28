import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import SectionLabel from '@/components/ui/SectionLabel';
import type { Character, Storyline } from '@/types/models';
import WikiAvatar from './WikiAvatar';

export default function CharacterDetail({
    character,
    storylines = [],
    onEdit,
}: {
    character: Character;
    storylines?: Storyline[];
    onEdit?: () => void;
}) {
    const { t } = useTranslation('wiki');

    const storylineLabels = character.storylines?.length
        ? character.storylines
              .map((id) => {
                  const storyline = storylines.find((s) => s.id === id);
                  return storyline
                      ? storyline.name
                      : t('storylineLabel', { name: id });
              })
              .join(', ')
        : null;

    const hasMetadata =
        !!character.first_appearance_chapter || !!storylineLabels;

    return (
        <div className="flex max-w-3xl flex-col gap-6">
            {/* Header */}
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-4">
                    <WikiAvatar
                        name={character.name}
                        tab="characters"
                        size="lg"
                    />
                    <div>
                        <h2 className="font-serif text-2xl font-semibold tracking-[-0.01em] text-ink">
                            {character.name}
                        </h2>
                        {character.aliases && character.aliases.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span className="text-[12px] text-ink-muted">
                                    {t('alsoKnownAs')}
                                </span>
                                {character.aliases.map((alias) => (
                                    <Badge key={alias} variant="secondary">
                                        {alias}
                                    </Badge>
                                ))}
                            </div>
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

            {/* Description */}
            {character.description && (
                <Card className="flex flex-col gap-3 p-6">
                    <SectionLabel>{t('description')}</SectionLabel>
                    <div className="flex flex-col gap-3 text-[14px] leading-relaxed text-ink">
                        {character.description
                            .split('\n')
                            .filter(Boolean)
                            .map((paragraph, i) => (
                                <p key={i}>{paragraph}</p>
                            ))}
                    </div>
                </Card>
            )}

            {/* Metadata */}
            {hasMetadata && (
                <Card className="flex gap-12 p-6">
                    {character.first_appearance_chapter && (
                        <div>
                            <SectionLabel className="mb-1">
                                {t('firstAppearance')}
                            </SectionLabel>
                            <p className="text-[13px] text-ink">
                                {t('chapterEntry', {
                                    order: character.first_appearance_chapter
                                        .reader_order,
                                    title: character.first_appearance_chapter
                                        .title,
                                })}
                            </p>
                        </div>
                    )}
                    {storylineLabels && (
                        <div>
                            <SectionLabel className="mb-1">
                                {t('storylinesHeading')}
                            </SectionLabel>
                            <p className="text-[13px] text-ink">
                                {storylineLabels}
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
                        {character.is_ai_extracted
                            ? t('aiExtracted')
                            : t('edit.manualEntry')}
                    </p>
                </div>
            </Card>

            {/* Appears In */}
            {character.chapters && character.chapters.length > 0 && (
                <Card className="flex flex-col gap-3 p-6">
                    <SectionLabel>{t('appearsIn')}</SectionLabel>
                    <div className="flex flex-col">
                        {character.chapters.map((chapter, index) => (
                            <div
                                key={chapter.id}
                                className={`flex items-center gap-4 py-3 ${index > 0 ? 'border-t border-border-subtle' : ''}`}
                            >
                                <span className="w-44 shrink-0 text-[13px] text-ink">
                                    {chapter.reader_order}. {chapter.title}
                                </span>
                                <Badge
                                    variant={
                                        chapter.pivot.role === 'protagonist'
                                            ? 'warning'
                                            : 'secondary'
                                    }
                                >
                                    {t(`role.${chapter.pivot.role}`)}
                                </Badge>
                                {chapter.pivot.notes && (
                                    <span className="text-[12px] text-ink-muted italic">
                                        {chapter.pivot.notes}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}

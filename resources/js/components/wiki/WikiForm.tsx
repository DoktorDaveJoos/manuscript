import { router, useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    destroyCharacter,
    destroyEntry,
    storeCharacter,
    storeEntry,
    updateCharacter,
    updateEntry,
} from '@/actions/App/Http/Controllers/WikiController';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import type {
    Book,
    Chapter,
    Character,
    Storyline,
    WikiEntry,
} from '@/types/models';
import ChapterCombobox from './ChapterCombobox';
import StorylineCombobox from './StorylineCombobox';
import type { WikiTab } from './WikiTabBar';

type WikiFormProps = {
    tab: WikiTab;
    book: Book;
    storylines: Storyline[];
    onCancel: () => void;
    onSuccess: () => void;
    item?: Character | WikiEntry;
};

const wikiLabelClass = 'text-[11px] text-ink-faint';

export default function WikiForm({
    tab,
    book,
    storylines,
    onCancel,
    onSuccess,
    item,
}: WikiFormProps) {
    const { t } = useTranslation('wiki');
    const isCharacter = tab === 'characters';
    const isEditing = !!item;

    // Flatten chapters from all storylines, sorted by reader_order
    const chapters = useMemo<Chapter[]>(() => {
        const all: Chapter[] = [];
        for (const sl of storylines) {
            if (Array.isArray(sl.chapters)) {
                for (const ch of sl.chapters) {
                    if (ch?.id != null) all.push(ch);
                }
            }
        }
        return all.sort((a, b) => a.reader_order - b.reader_order);
    }, [storylines]);

    // Existing chapter IDs for edit mode
    const existingChapterIds = useMemo(() => {
        if (!item) return [] as number[];
        const chaps = 'chapters' in item ? item.chapters : undefined;
        return (chaps ?? []).filter((ch) => ch?.id != null).map((ch) => ch.id);
    }, [item]);

    const characterItem = isCharacter && item ? (item as Character) : null;
    const entryItem = !isCharacter && item ? (item as WikiEntry) : null;

    const characterForm = useForm({
        name: characterItem ? characterItem.name : '',
        description: item?.description ?? '',
        aliases: characterItem
            ? (characterItem.aliases ?? [])
            : ([] as string[]),
        storylines: characterItem
            ? (characterItem.storylines ?? [])
            : ([] as number[]),
        role: (characterItem && characterItem.chapters?.length
            ? (characterItem.chapters[0]?.pivot?.role ?? 'supporting')
            : 'supporting') as string,
        chapter_ids: existingChapterIds,
    });

    const entryForm = useForm({
        name: entryItem ? entryItem.name : '',
        description: item?.description ?? '',
        kind: entryItem ? entryItem.kind : (tab as string),
        type: entryItem ? (entryItem.type ?? '') : '',
        chapter_ids: existingChapterIds,
    });

    const currentForm = isCharacter ? characterForm : entryForm;
    const isDirty = isCharacter ? characterForm.isDirty : entryForm.isDirty;

    // Auto-derive first_appearance from chapter_ids (lowest reader_order wins)
    const chapterIds = currentForm.data.chapter_ids ?? [];
    const firstAppearance = useMemo<number | null>(() => {
        if (chapterIds.length === 0) return null;
        const selected = chapters.filter(
            (ch) => ch?.id != null && chapterIds.includes(ch.id),
        );
        if (selected.length === 0) return null;
        const earliest = selected.reduce((min, ch) =>
            ch.reader_order < min.reader_order ? ch : min,
        );
        return earliest?.id ?? null;
    }, [chapterIds, chapters]);

    // Alias handling
    const [aliasInput, setAliasInput] = useState('');

    const addAlias = (raw: string) => {
        const value = raw.trim();
        if (value && !characterForm.data.aliases.includes(value)) {
            characterForm.setData('aliases', [
                ...characterForm.data.aliases,
                value,
            ]);
        }
        setAliasInput('');
    };

    const handleAliasKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addAlias(aliasInput);
        }
    };

    const handleAliasBlur = () => {
        if (aliasInput.trim()) {
            addAlias(aliasInput);
        }
    };

    const removeAlias = (alias: string) => {
        characterForm.setData(
            'aliases',
            characterForm.data.aliases.filter((a) => a !== alias),
        );
    };

    // Togglers
    const toggleStoryline = (id: number) => {
        const current = characterForm.data.storylines;
        characterForm.setData(
            'storylines',
            current.includes(id)
                ? current.filter((s) => s !== id)
                : [...current, id],
        );
    };

    const toggleChapter = (id: number) => {
        if (isCharacter) {
            const current = characterForm.data.chapter_ids;
            characterForm.setData(
                'chapter_ids',
                current.includes(id)
                    ? current.filter((c) => c !== id)
                    : [...current, id],
            );
        } else {
            const current = entryForm.data.chapter_ids;
            entryForm.setData(
                'chapter_ids',
                current.includes(id)
                    ? current.filter((c) => c !== id)
                    : [...current, id],
            );
        }
    };

    // Submit
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...currentForm.data,
            first_appearance: firstAppearance,
        };

        if (isEditing) {
            if (isCharacter) {
                router.patch(
                    updateCharacter.url({ book, character: item?.id }),
                    payload,
                    { onSuccess },
                );
            } else {
                router.patch(
                    updateEntry.url({ book, wikiEntry: item?.id }),
                    payload,
                    { onSuccess },
                );
            }
        } else {
            if (isCharacter) {
                router.post(storeCharacter.url(book), payload, { onSuccess });
            } else {
                router.post(storeEntry.url(book), payload, { onSuccess });
            }
        }
    };

    // Delete
    const handleDelete = () => {
        if (isCharacter) {
            router.delete(destroyCharacter.url({ book, character: item?.id }), {
                onSuccess,
            });
        } else {
            router.delete(destroyEntry.url({ book, wikiEntry: item?.id }), {
                onSuccess,
            });
        }
    };

    const titleKey = `create.${tab === 'characters' ? 'character' : tab}`;

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-8">
            {/* Header */}
            {isEditing ? (
                <div className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        {isDirty && (
                            <span className="flex items-center gap-1.5 text-[12px] text-ink-muted">
                                <span className="inline-block h-1.5 w-1.5 rounded-full bg-ai-green" />
                                {t('edit.editing')} · {t('edit.unsavedChanges')}
                            </span>
                        )}
                        <div className="ml-auto flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                type="button"
                                onClick={onCancel}
                            >
                                {t('create.cancel')}
                            </Button>
                            <Button
                                variant="primary"
                                size="sm"
                                type="submit"
                                disabled={currentForm.processing}
                            >
                                {t('edit.save')}
                            </Button>
                        </div>
                    </div>
                    <Input
                        type="text"
                        value={
                            isCharacter
                                ? characterForm.data.name
                                : entryForm.data.name
                        }
                        onChange={(e) => {
                            if (isCharacter) {
                                characterForm.setData('name', e.target.value);
                            } else {
                                entryForm.setData('name', e.target.value);
                            }
                        }}
                        className="text-xl font-semibold text-ink"
                    />
                </div>
            ) : (
                <div className="flex items-center justify-between">
                    <h2 className="font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink">
                        {t(titleKey)}
                    </h2>
                    <Button variant="ghost" type="button" onClick={onCancel}>
                        {t('create.cancel')}
                    </Button>
                </div>
            )}

            {/* Name (create mode only) */}
            {!isEditing && (
                <FormField
                    label={t('field.name')}
                    error={
                        isCharacter
                            ? characterForm.errors.name
                            : entryForm.errors.name
                    }
                    labelClassName={wikiLabelClass}
                >
                    <Input
                        type="text"
                        value={
                            isCharacter
                                ? characterForm.data.name
                                : entryForm.data.name
                        }
                        onChange={(e) =>
                            isCharacter
                                ? characterForm.setData('name', e.target.value)
                                : entryForm.setData('name', e.target.value)
                        }
                        placeholder={t('field.namePlaceholder')}
                        autoFocus
                    />
                </FormField>
            )}

            {/* Aliases (characters only) */}
            {isCharacter && (
                <FormField
                    label={t('field.aliases')}
                    labelClassName={wikiLabelClass}
                >
                    <div className="flex flex-wrap items-center gap-1.5 rounded-md border border-border bg-white px-3 py-2 dark:bg-surface-card">
                        {characterForm.data.aliases.map((alias) => (
                            <Badge
                                key={alias}
                                variant="secondary"
                                className="gap-1"
                            >
                                {alias}
                                <button
                                    type="button"
                                    onClick={() => removeAlias(alias)}
                                    className="text-ink-faint transition-colors hover:text-ink"
                                >
                                    <X size={10} />
                                </button>
                            </Badge>
                        ))}
                        <input
                            type="text"
                            value={aliasInput}
                            onChange={(e) => setAliasInput(e.target.value)}
                            onKeyDown={handleAliasKeyDown}
                            onBlur={handleAliasBlur}
                            placeholder={
                                characterForm.data.aliases.length === 0
                                    ? t('field.aliasPlaceholder')
                                    : ''
                            }
                            className="min-w-[80px] flex-1 bg-transparent text-[13px] text-ink placeholder:text-ink-faint focus:outline-none"
                        />
                    </div>
                </FormField>
            )}

            {/* Role (characters only) */}
            {isCharacter && (
                <FormField
                    label={t('field.role')}
                    labelClassName={wikiLabelClass}
                >
                    <Select
                        value={characterForm.data.role}
                        onChange={(e) =>
                            characterForm.setData('role', e.target.value)
                        }
                    >
                        <option value="protagonist">
                            {t('role.mainCharacter')}
                        </option>
                        <option value="supporting">
                            {t('role.supporting')}
                        </option>
                        <option value="mentioned">{t('role.mentioned')}</option>
                    </Select>
                </FormField>
            )}

            {/* Type (entries only) */}
            {!isCharacter && (
                <FormField
                    label={t('field.type')}
                    labelClassName={wikiLabelClass}
                >
                    <Input
                        type="text"
                        value={entryForm.data.type}
                        onChange={(e) =>
                            entryForm.setData('type', e.target.value)
                        }
                        placeholder={t('field.typePlaceholder')}
                    />
                </FormField>
            )}

            {/* Description */}
            <FormField
                label={t('field.description')}
                labelClassName={wikiLabelClass}
            >
                <Textarea
                    value={
                        isCharacter
                            ? characterForm.data.description
                            : entryForm.data.description
                    }
                    onChange={(e) =>
                        isCharacter
                            ? characterForm.setData(
                                  'description',
                                  e.target.value,
                              )
                            : entryForm.setData('description', e.target.value)
                    }
                    placeholder={
                        isCharacter
                            ? t('field.descriptionPlaceholder')
                            : t('field.entryDescriptionPlaceholder')
                    }
                    rows={isEditing ? 8 : 6}
                />
            </FormField>

            {/* Storylines (characters only) */}
            {isCharacter && storylines.length > 0 && (
                <FormField
                    label={t('field.storylines')}
                    labelClassName={wikiLabelClass}
                >
                    <StorylineCombobox
                        storylines={storylines}
                        selectedIds={characterForm.data.storylines}
                        onToggle={toggleStoryline}
                    />
                </FormField>
            )}

            <div className="border-t border-border-subtle" />

            {/* Chapter appearances */}
            <ChapterCombobox
                chapters={chapters}
                selectedIds={currentForm.data.chapter_ids}
                onToggle={toggleChapter}
            />

            {/* Source (edit mode only) */}
            {isEditing && (
                <>
                    <div className="border-t border-border-subtle" />
                    <div className="flex items-center gap-1.5 text-[13px] text-ink-muted">
                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-ai-green" />
                        {item?.is_ai_extracted
                            ? t('aiExtracted')
                            : t('edit.manualEntry')}
                    </div>
                </>
            )}

            {/* Submit button (create mode only) */}
            {!isEditing && (
                <div className="flex items-center gap-4">
                    <Button
                        variant="primary"
                        type="submit"
                        disabled={currentForm.processing}
                    >
                        {isCharacter
                            ? t('create.submit')
                            : t('create.submitEntry')}
                    </Button>
                </div>
            )}

            {/* Delete (edit mode only) */}
            {isEditing && (
                <div className="border-t border-border-subtle pt-6">
                    <Button
                        variant="ghost"
                        size="sm"
                        type="button"
                        onClick={handleDelete}
                        className="text-delete hover:text-delete/80"
                    >
                        {t('edit.delete')}
                    </Button>
                </div>
            )}
        </form>
    );
}

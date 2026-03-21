import { router, useForm } from '@inertiajs/react';
import { BookOpen, X } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    destroyCharacter,
    destroyEntry,
    updateCharacter,
    updateEntry,
} from '@/actions/App/Http/Controllers/WikiController';
import Button from '@/components/ui/Button';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import Select from '@/components/ui/Select';
import Textarea from '@/components/ui/Textarea';
import type { Book, Character, Storyline, WikiEntry } from '@/types/models';
import type { WikiTab } from './WikiTabBar';

type Props = {
    item: Character | WikiEntry;
    tab: WikiTab;
    book: Book;
    storylines: Storyline[];
    onCancel: () => void;
    onSuccess: () => void;
};

function isCharacter(
    item: Character | WikiEntry,
    tab: WikiTab,
): item is Character {
    return tab === 'characters';
}

const wikiLabelClass = 'text-[11px] text-ink-faint';

export default function WikiEditForm({
    item,
    tab,
    book,
    storylines,
    onCancel,
    onSuccess,
}: Props) {
    const { t } = useTranslation('wiki');
    const isChar = isCharacter(item, tab);

    const characterForm = useForm({
        name: isChar ? item.name : '',
        description: item.description ?? '',
        aliases: isChar ? (item.aliases ?? []) : [],
        storylines: isChar ? (item.storylines ?? []) : [],
        role:
            isChar && item.chapters?.length
                ? (item.chapters[0]?.pivot?.role ?? '')
                : '',
    });

    const entryForm = useForm({
        name: !isChar ? item.name : '',
        description: item.description ?? '',
        kind: !isChar ? (item as WikiEntry).kind : '',
        type: !isChar ? ((item as WikiEntry).type ?? '') : '',
    });

    const [aliasInput, setAliasInput] = useState('');
    const isDirty = isChar ? characterForm.isDirty : entryForm.isDirty;

    const handleAliasKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const value = aliasInput.trim();
            if (value && !characterForm.data.aliases.includes(value)) {
                characterForm.setData('aliases', [
                    ...characterForm.data.aliases,
                    value,
                ]);
            }
            setAliasInput('');
        }
    };

    const removeAlias = (alias: string) => {
        characterForm.setData(
            'aliases',
            characterForm.data.aliases.filter((a) => a !== alias),
        );
    };

    const toggleStoryline = (id: number) => {
        const current = characterForm.data.storylines;
        characterForm.setData(
            'storylines',
            current.includes(id)
                ? current.filter((s) => s !== id)
                : [...current, id],
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isChar) {
            router.patch(
                updateCharacter.url({ book, character: item.id }),
                characterForm.data,
                { onSuccess },
            );
        } else {
            router.patch(
                updateEntry.url({ book, wikiEntry: item.id }),
                entryForm.data,
                { onSuccess },
            );
        }
    };

    const handleDelete = () => {
        if (isChar) {
            router.delete(destroyCharacter.url({ book, character: item.id }), {
                onSuccess,
            });
        } else {
            router.delete(destroyEntry.url({ book, wikiEntry: item.id }), {
                onSuccess,
            });
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-8">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Input
                        type="text"
                        value={
                            isChar
                                ? characterForm.data.name
                                : entryForm.data.name
                        }
                        onChange={(e) => {
                            if (isChar) {
                                characterForm.setData('name', e.target.value);
                            } else {
                                entryForm.setData('name', e.target.value);
                            }
                        }}
                        className="font-serif text-[28px]"
                    />
                    {isDirty && (
                        <span className="flex items-center gap-1.5 text-[12px] text-ink-muted">
                            <span className="inline-block h-1.5 w-1.5 rounded-full bg-ai-green" />
                            {t('edit.editing')} · {t('edit.unsavedChanges')}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-2">
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
                        disabled={
                            isChar
                                ? characterForm.processing
                                : entryForm.processing
                        }
                    >
                        {t('edit.save')}
                    </Button>
                </div>
            </div>

            {/* Aliases — characters only */}
            {isChar && (
                <FormField
                    label={t('field.aliases')}
                    labelClassName={wikiLabelClass}
                >
                    <div className="flex flex-wrap items-center gap-1.5">
                        {characterForm.data.aliases.map((alias) => (
                            <span
                                key={alias}
                                className="flex items-center gap-1.5 rounded bg-neutral-bg px-2.5 py-1 text-[12px] text-ink-soft"
                            >
                                {alias}
                                <button
                                    type="button"
                                    onClick={() => removeAlias(alias)}
                                >
                                    <X size={10} className="text-ink-faint" />
                                </button>
                            </span>
                        ))}
                        <input
                            type="text"
                            value={aliasInput}
                            onChange={(e) => setAliasInput(e.target.value)}
                            onKeyDown={handleAliasKeyDown}
                            placeholder={t('edit.addAlias')}
                            className="min-w-[80px] bg-transparent text-[12px] text-ink-soft placeholder:text-ink-faint focus:outline-none"
                        />
                    </div>
                </FormField>
            )}

            {/* Type — wiki entries only */}
            {!isChar && (
                <FormField
                    label={t('field.type')}
                    labelClassName={wikiLabelClass}
                >
                    <Input
                        type="text"
                        value={entryForm.data.type}
                        onChange={(e) => {
                            entryForm.setData('type', e.target.value);
                        }}
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
                        isChar
                            ? characterForm.data.description
                            : entryForm.data.description
                    }
                    onChange={(e) =>
                        isChar
                            ? characterForm.setData(
                                  'description',
                                  e.target.value,
                              )
                            : entryForm.setData('description', e.target.value)
                    }
                    rows={4}
                />
            </FormField>

            <div className="border-t border-border-subtle" />

            {/* Meta section */}
            <div className="flex gap-12">
                {/* Role — characters only */}
                {isChar && (
                    <FormField
                        label={t('field.role')}
                        labelClassName={wikiLabelClass}
                    >
                        <Select
                            value={characterForm.data.role}
                            onChange={(e) =>
                                characterForm.setData('role', e.target.value)
                            }
                            className="w-auto"
                        >
                            <option value="protagonist">
                                {t('role.mainCharacter')}
                            </option>
                            <option value="supporting">
                                {t('role.supporting')}
                            </option>
                            <option value="mentioned">
                                {t('role.mentioned')}
                            </option>
                        </Select>
                    </FormField>
                )}

                {/* Storylines — characters only */}
                {isChar && storylines.length > 0 && (
                    <FormField
                        label={t('field.storylines')}
                        labelClassName={wikiLabelClass}
                    >
                        <div className="flex flex-wrap gap-1.5">
                            {storylines.map((s) => {
                                const selected =
                                    characterForm.data.storylines.includes(
                                        s.id,
                                    );
                                return (
                                    <button
                                        key={s.id}
                                        type="button"
                                        onClick={() => toggleStoryline(s.id)}
                                        className={`flex items-center gap-1 rounded-full px-3 py-1.5 text-[12px] font-medium transition-colors ${
                                            selected
                                                ? 'bg-ink text-surface'
                                                : 'bg-neutral-bg text-ink-soft hover:bg-border'
                                        }`}
                                    >
                                        {s.name}
                                        {selected && (
                                            <X
                                                size={12}
                                                className="text-ink-muted"
                                            />
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </FormField>
                )}

                {/* Source */}
                <FormField label={t('source')} labelClassName={wikiLabelClass}>
                    <span className="flex items-center gap-1.5 text-[13px] text-ink-muted">
                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-ai-green" />
                        {item.is_ai_extracted
                            ? t('aiExtracted')
                            : t('edit.manualEntry')}
                    </span>
                </FormField>
            </div>

            <div className="border-t border-border-subtle" />

            {/* Appears In — empty state */}
            <div>
                <h3 className="mb-3 text-[11px] font-medium tracking-[0.08em] text-ink-faint uppercase">
                    {t('appearsIn')}
                </h3>
                {item.chapters && item.chapters.length > 0 ? (
                    <div className="flex flex-col">
                        {item.chapters.map((chapter) => (
                            <div
                                key={chapter.id}
                                className="flex items-center gap-4 border-t border-border-subtle py-3"
                            >
                                <span className="text-[13px] text-ink">
                                    {chapter.reader_order}. {chapter.title}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                        <BookOpen size={20} className="text-ink-faint" />
                        <p className="text-[13px] text-ink-muted">
                            {t('edit.noAppearances')}
                        </p>
                        <p className="text-[12px] text-ink-faint">
                            {t('edit.appearancesHint')}
                        </p>
                    </div>
                )}
            </div>

            {/* Delete */}
            <div className="border-t border-border-subtle pt-6">
                <button
                    type="button"
                    onClick={handleDelete}
                    className="text-[12px] font-medium text-delete transition-colors hover:text-delete/80"
                >
                    {t('edit.delete')}
                </button>
            </div>
        </form>
    );
}

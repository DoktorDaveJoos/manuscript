import { storeCharacter, storeEntry } from '@/actions/App/Http/Controllers/WikiController';
import type { Book, Storyline } from '@/types/models';
import { router, useForm } from '@inertiajs/react';
import { Camera, X } from 'lucide-react';
import { type KeyboardEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { WikiTab } from './WikiTabBar';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import Textarea from '@/components/ui/Textarea';
import Select from '@/components/ui/Select';

type Props = {
    type: WikiTab;
    book: Book;
    storylines: Storyline[];
    onCancel: () => void;
    onSuccess: () => void;
};

export default function WikiCreateForm({ type, book, storylines, onCancel, onSuccess }: Props) {
    const { t } = useTranslation('wiki');
    const isCharacter = type === 'characters';

    const characterForm = useForm({
        name: '',
        description: '',
        aliases: [] as string[],
        storylines: [] as number[],
        role: 'supporting' as string,
    });

    const entryForm = useForm({
        name: '',
        description: '',
        kind: type as string,
        type: '',
    });

    const [aliasInput, setAliasInput] = useState('');

    const titleKey = `create.${type === 'characters' ? 'character' : type}`;

    const handleAliasKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const value = aliasInput.trim();
            if (value && !characterForm.data.aliases.includes(value)) {
                characterForm.setData('aliases', [...characterForm.data.aliases, value]);
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
            current.includes(id) ? current.filter((s) => s !== id) : [...current, id],
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isCharacter) {
            router.post(storeCharacter.url(book), characterForm.data, {
                onSuccess,
            });
        } else {
            router.post(storeEntry.url(book), entryForm.data, {
                onSuccess,
            });
        }
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-8">
            {/* Header */}
            <div className="flex items-center justify-between">
                <h2 className="font-serif text-[30px] leading-[1.2] text-ink">{t(titleKey)}</h2>
                <Button variant="ghost" type="button" onClick={onCancel}>
                    {t('create.cancel')}
                </Button>
            </div>

            {/* Avatar placeholder — characters only */}
            {isCharacter && (
                <div className="flex items-center gap-3">
                    <div className="flex h-[52px] w-[52px] items-center justify-center rounded-full bg-neutral-bg">
                        <Camera size={20} className="text-ink-faint" />
                    </div>
                    <span className="text-[13px] text-ink-faint">{t('create.uploadPhoto')}</span>
                </div>
            )}

            {/* Name */}
            <div>
                <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                    {t('field.name')}
                </label>
                <Input
                    type="text"
                    value={isCharacter ? characterForm.data.name : entryForm.data.name}
                    onChange={(e) =>
                        isCharacter
                            ? characterForm.setData('name', e.target.value)
                            : entryForm.setData('name', e.target.value)
                    }
                    placeholder={t('field.namePlaceholder')}
                    autoFocus
                />
                {(isCharacter ? characterForm.errors.name : entryForm.errors.name) && (
                    <p className="mt-1 text-[12px] text-red-600">
                        {isCharacter ? characterForm.errors.name : entryForm.errors.name}
                    </p>
                )}
            </div>

            {/* Aliases — characters only */}
            {isCharacter && (
                <div>
                    <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('field.aliases')}
                    </label>
                    <div className="flex flex-wrap items-center gap-1.5 rounded-md bg-neutral-bg px-3 py-2">
                        {characterForm.data.aliases.map((alias) => (
                            <span
                                key={alias}
                                className="flex items-center gap-1 rounded bg-border px-2.5 py-1 text-[12px] text-ink-soft"
                            >
                                {alias}
                                <button type="button" onClick={() => removeAlias(alias)}>
                                    <X size={10} className="text-ink-faint" />
                                </button>
                            </span>
                        ))}
                        <input
                            type="text"
                            value={aliasInput}
                            onChange={(e) => setAliasInput(e.target.value)}
                            onKeyDown={handleAliasKeyDown}
                            placeholder={characterForm.data.aliases.length === 0 ? t('field.aliasPlaceholder') : ''}
                            className="min-w-[80px] flex-1 bg-transparent text-[14px] text-ink placeholder:text-ink-faint focus:outline-none"
                        />
                    </div>
                </div>
            )}

            {/* Role — characters only */}
            {isCharacter && (
                <div>
                    <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('field.role')}
                    </label>
                    <Select
                        value={characterForm.data.role}
                        onChange={(e) => characterForm.setData('role', e.target.value)}
                    >
                        <option value="protagonist">{t('role.mainCharacter')}</option>
                        <option value="supporting">{t('role.supporting')}</option>
                        <option value="mentioned">{t('role.mentioned')}</option>
                    </Select>
                </div>
            )}

            {/* Type — wiki entries only */}
            {!isCharacter && (
                <div>
                    <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('field.type')}
                    </label>
                    <Input
                        type="text"
                        value={entryForm.data.type}
                        onChange={(e) => entryForm.setData('type', e.target.value)}
                        placeholder={t('field.typePlaceholder')}
                    />
                </div>
            )}

            {/* Description */}
            <div>
                <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                    {t('field.description')}
                </label>
                <Textarea
                    value={isCharacter ? characterForm.data.description : entryForm.data.description}
                    onChange={(e) =>
                        isCharacter
                            ? characterForm.setData('description', e.target.value)
                            : entryForm.setData('description', e.target.value)
                    }
                    placeholder={
                        isCharacter ? t('field.descriptionPlaceholder') : t('field.entryDescriptionPlaceholder')
                    }
                    rows={4}
                />
            </div>

            {/* Storylines — characters only */}
            {isCharacter && storylines.length > 0 && (
                <div>
                    <label className="mb-2 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                        {t('field.storylines')}
                    </label>
                    <div className="flex flex-wrap gap-2">
                        {storylines.map((s) => {
                            const selected = characterForm.data.storylines.includes(s.id);
                            return (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => toggleStoryline(s.id)}
                                    className={`flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[12px] font-medium transition-colors ${
                                        selected
                                            ? 'bg-ink text-surface'
                                            : 'bg-neutral-bg text-ink-soft hover:bg-border'
                                    }`}
                                >
                                    {s.name}
                                    {selected && <X size={12} />}
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Actions */}
            <div className="flex items-center gap-4">
                <Button variant="primary" type="submit" disabled={isCharacter ? characterForm.processing : entryForm.processing}>
                    {isCharacter ? t('create.submit') : t('create.submitEntry')}
                </Button>
            </div>
        </form>
    );
}

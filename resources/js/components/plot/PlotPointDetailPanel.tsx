import { router } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import Drawer from '@/components/ui/Drawer';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import Textarea from '@/components/ui/Textarea';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import { STATUS_PILL_OPTIONS } from '@/lib/plot-constants';
import type {
    Character,
    CharacterPlotPointRole,
    PlotPoint,
    PlotPointStatus,
    PlotPointType,
} from '@/types/models';
import StatusPillGroup from './StatusPillGroup';

const TYPE_OPTIONS: {
    value: PlotPointType;
    labelKey: string;
    activeClass: string;
}[] = [
    {
        value: 'setup',
        labelKey: 'type.setup',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'conflict',
        labelKey: 'type.conflict',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'turning_point',
        labelKey: 'typeShort.turning_point',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'resolution',
        labelKey: 'type.resolution',
        activeClass: 'bg-ink/10 text-ink',
    },
    {
        value: 'worldbuilding',
        labelKey: 'typeShort.worldbuilding',
        activeClass: 'bg-ink/10 text-ink',
    },
];

const ROLE_OPTIONS: CharacterPlotPointRole[] = [
    'key',
    'supporting',
    'mentioned',
];

type Props = {
    plotPoint: PlotPoint;
    bookId: number;
    characters: Character[];
    onClose: () => void;
    onDelete: (plotPointId: number) => void;
    onTitleChange?: (title: string) => void;
};

export default function PlotPointDetailPanel({
    plotPoint,
    bookId,
    characters,
    onClose,
    onDelete,
    onTitleChange,
}: Props) {
    const { t } = useTranslation('plot');
    const [title, setTitle] = useState(plotPoint.title);
    const [description, setDescription] = useState(plotPoint.description ?? '');
    const [selectorOpen, setSelectorOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const selectorRef = useRef<HTMLDivElement>(null);
    const searchInputRef = useRef<HTMLInputElement>(null);

    const linkedCharacters = plotPoint.characters ?? [];

    const patchPlotPoint = useCallback(
        (
            data: Record<
                string,
                string | number | { id: number; role: string }[]
            >,
        ) => {
            router.patch(`/books/${bookId}/plot-points/${plotPoint.id}`, data, {
                preserveScroll: true,
            });
        },
        [bookId, plotPoint.id],
    );

    const debouncedPatchTitle = useDebouncedCallback(
        (title: string) => patchPlotPoint({ title }),
        500,
    );

    const debouncedPatchDescription = useDebouncedCallback(
        (description: string) => patchPlotPoint({ description }),
        500,
    );

    const handleTitleChange = (value: string) => {
        setTitle(value);
        onTitleChange?.(value);
        debouncedPatchTitle(value);
    };

    const handleDescriptionChange = (value: string) => {
        setDescription(value);
        debouncedPatchDescription(value);
    };

    const handleTypeChange = useCallback(
        (type: PlotPointType) => {
            patchPlotPoint({ type });
        },
        [patchPlotPoint],
    );

    const handleStatusChange = useCallback(
        (status: PlotPointStatus) => {
            router.patch(
                `/books/${bookId}/plot-points/${plotPoint.id}/status`,
                { status },
                { preserveScroll: true },
            );
        },
        [bookId, plotPoint.id],
    );

    const patchCharacters = useCallback(
        (
            chars: {
                id: number;
                role: CharacterPlotPointRole;
            }[],
        ) => {
            patchPlotPoint({ characters: chars });
        },
        [patchPlotPoint],
    );

    const handleAddCharacter = useCallback(
        (characterId: number) => {
            const existing = linkedCharacters.map((c) => ({
                id: c.id,
                role: c.pivot.role,
            }));
            patchCharacters([...existing, { id: characterId, role: 'key' }]);
            setSelectorOpen(false);
            setSearchQuery('');
        },
        [linkedCharacters, patchCharacters],
    );

    const handleRemoveCharacter = useCallback(
        (characterId: number) => {
            const remaining = linkedCharacters
                .filter((c) => c.id !== characterId)
                .map((c) => ({ id: c.id, role: c.pivot.role }));
            patchCharacters(remaining);
        },
        [linkedCharacters, patchCharacters],
    );

    const handleRoleChange = useCallback(
        (characterId: number, role: CharacterPlotPointRole) => {
            const updated = linkedCharacters.map((c) => ({
                id: c.id,
                role: c.id === characterId ? role : c.pivot.role,
            }));
            patchCharacters(updated);
        },
        [linkedCharacters, patchCharacters],
    );

    const availableCharacters = useMemo(() => {
        const linkedIds = new Set(linkedCharacters.map((c) => c.id));
        const filtered = characters.filter((c) => !linkedIds.has(c.id));

        if (!searchQuery.trim()) return filtered;

        const query = searchQuery.toLowerCase();
        return filtered.filter((c) => c.name.toLowerCase().includes(query));
    }, [characters, linkedCharacters, searchQuery]);

    // Close selector on outside click
    useEffect(() => {
        if (!selectorOpen) return;

        function handleClickOutside(e: MouseEvent) {
            if (
                selectorRef.current &&
                !selectorRef.current.contains(e.target as Node)
            ) {
                setSelectorOpen(false);
                setSearchQuery('');
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [selectorOpen]);

    // Focus search input when selector opens
    useEffect(() => {
        if (selectorOpen) {
            searchInputRef.current?.focus();
        }
    }, [selectorOpen]);

    return (
        <Drawer onClose={onClose}>
            <PanelHeader title={t('plotPoint.header')} onClose={onClose} />

            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                {/* Title */}
                <FormField label={t('plotPoint.title')}>
                    <Input
                        type="text"
                        value={title}
                        onChange={(e) => handleTitleChange(e.target.value)}
                    />
                </FormField>

                {/* Description */}
                <FormField label={t('plotPoint.description')}>
                    <Textarea
                        value={description}
                        onChange={(e) =>
                            handleDescriptionChange(e.target.value)
                        }
                        rows={4}
                        placeholder={t('plotPoint.descriptionPlaceholder')}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.descriptionHelper',
                            'Summarize what happens at this turning point.',
                        )}
                    </span>
                </FormField>

                {/* Type */}
                <FormField label={t('plotPoint.type')}>
                    <StatusPillGroup
                        options={TYPE_OPTIONS}
                        value={plotPoint.type}
                        onChange={handleTypeChange}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.typeHelper',
                            'How this point functions in the narrative arc.',
                        )}
                    </span>
                </FormField>

                {/* Status */}
                <FormField label={t('plotPoint.status')}>
                    <StatusPillGroup
                        options={STATUS_PILL_OPTIONS}
                        value={plotPoint.status}
                        onChange={handleStatusChange}
                    />
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.statusHelper',
                            'Track whether this plot point has been resolved.',
                        )}
                    </span>
                </FormField>

                {/* Divider */}
                <div className="h-px bg-border" />

                {/* Characters */}
                <div className="flex flex-col gap-2.5">
                    <div className="flex items-center justify-between">
                        <SectionLabel>{t('plotPoint.characters')}</SectionLabel>
                        <div ref={selectorRef} className="relative">
                            <button
                                type="button"
                                onClick={() => setSelectorOpen(!selectorOpen)}
                                className="flex items-center justify-center text-ink-muted transition-colors hover:text-ink-soft"
                                aria-label={t('detailPanel.addCharacter')}
                            >
                                <Plus size={14} />
                            </button>

                            {selectorOpen && (
                                <div className="absolute top-full right-0 z-50 mt-1 w-[220px] rounded-lg bg-surface-card shadow-[0_4px_24px_#0000001F,0_0_0_1px_#0000000A]">
                                    <div className="border-b border-border p-2">
                                        <input
                                            ref={searchInputRef}
                                            type="text"
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                            placeholder={t(
                                                'plotPoint.searchCharacters',
                                            )}
                                            className="w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[12px] text-ink outline-none placeholder:text-ink-muted focus:border-accent"
                                        />
                                    </div>
                                    <div className="max-h-[180px] overflow-y-auto p-1">
                                        {availableCharacters.length > 0 ? (
                                            availableCharacters.map((char) => (
                                                <button
                                                    key={char.id}
                                                    type="button"
                                                    onClick={() =>
                                                        handleAddCharacter(
                                                            char.id,
                                                        )
                                                    }
                                                    className="flex w-full items-center gap-2 rounded-[5px] px-2.5 py-2 text-left text-[12px] font-medium text-ink transition-colors hover:bg-neutral-bg"
                                                >
                                                    {char.name}
                                                </button>
                                            ))
                                        ) : (
                                            <div className="px-2.5 py-2 text-[12px] text-ink-muted">
                                                {t(
                                                    'plotPoint.noAvailableCharacters',
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {linkedCharacters.length > 0 ? (
                        <div className="flex flex-wrap gap-1.5">
                            {linkedCharacters.map((char) => (
                                <span
                                    key={char.id}
                                    className="group flex items-center gap-1 rounded-full bg-neutral-bg px-2.5 py-1 text-[11px] font-medium text-ink-soft"
                                >
                                    {char.name}
                                    <select
                                        value={char.pivot.role}
                                        onChange={(e) =>
                                            handleRoleChange(
                                                char.id,
                                                e.target
                                                    .value as CharacterPlotPointRole,
                                            )
                                        }
                                        className="ml-0.5 cursor-pointer appearance-none border-none bg-transparent p-0 text-[10px] text-ink-muted underline decoration-dotted outline-none"
                                    >
                                        {ROLE_OPTIONS.map((role) => (
                                            <option key={role} value={role}>
                                                {t(
                                                    `detailPanel.characterRole.${role}`,
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleRemoveCharacter(char.id)
                                        }
                                        className="ml-0.5 flex items-center justify-center rounded-full opacity-0 transition-opacity group-hover:opacity-100"
                                        aria-label={t(
                                            'plotPoint.removeCharacter',
                                        )}
                                    >
                                        <X
                                            size={10}
                                            className="text-ink-muted hover:text-delete"
                                        />
                                    </button>
                                </span>
                            ))}
                        </div>
                    ) : (
                        <span className="text-[11px] text-ink-muted">
                            {t('detailPanel.noCharacters')}
                        </span>
                    )}
                    <span className="text-[10px] text-ink-faint italic">
                        {t(
                            'plotPoint.characterHelper',
                            'Tag characters involved in this plot point.',
                        )}
                    </span>
                </div>

                {/* Spacer */}
                <div className="flex-1" />

                {/* Delete */}
                <Button
                    variant="danger"
                    onClick={() => onDelete(plotPoint.id)}
                    className="w-full py-2.5"
                >
                    <Trash2 size={14} />
                    {t('plotPoint.delete')}
                </Button>
            </div>
        </Drawer>
    );
}

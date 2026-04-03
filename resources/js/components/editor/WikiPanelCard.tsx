import {
    ChevronDown,
    ChevronUp,
    Ellipsis,
    ExternalLink,
    Link2Off,
    Plus,
    Sparkles,
    UserCog,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Badge from '@/components/ui/Badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import SectionLabel from '@/components/ui/SectionLabel';
import WikiAvatar from '@/components/wiki/WikiAvatar';
import type { WikiTab } from '@/components/wiki/WikiTabBar';
import { useDebouncedCallback } from '@/hooks/useDebouncedCallback';
import { cn } from '@/lib/utils';
import type { Character, WikiEntry } from '@/types/models';

type WikiPanelCardProps = {
    entry: Character | WikiEntry;
    entryType: 'character' | 'wiki_entry';
    isConnected: boolean;
    isExpanded: boolean;
    onToggleExpand: () => void;
    onDismiss?: () => void;
    onConnect?: (role?: string) => void;
    onDisconnect?: () => void;
    onRoleChange?: (role: string) => void;
    onUpdate: (data: Record<string, unknown>) => void;
    chapterRole?: string;
    wikiUrl: string;
};

export function kindToTab(kind: string): WikiTab {
    if (kind === 'character') return 'characters';
    return kind as WikiTab;
}

export function capitalize(str: string): string {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function getKindLabel(entryType: string, entry: Character | WikiEntry): string {
    if (entryType === 'character') return 'Character';
    return capitalize((entry as WikiEntry).kind);
}

function getSubLabel(
    entry: Character | WikiEntry,
    entryType: string,
    chapterRole?: string,
): string | null {
    if (entryType === 'character' && chapterRole) {
        return capitalize(chapterRole);
    }
    if (entryType === 'wiki_entry') {
        return (entry as WikiEntry).type || null;
    }
    return null;
}

const ROLES = ['protagonist', 'supporting', 'mentioned'] as const;

export default function WikiPanelCard({
    entry,
    entryType,
    isConnected,
    isExpanded,
    onToggleExpand,
    onDismiss,
    onConnect,
    onDisconnect,
    onRoleChange,
    onUpdate,
    chapterRole,
    wikiUrl,
}: WikiPanelCardProps) {
    const { t } = useTranslation('wiki-panel');
    const tab = kindToTab(
        entryType === 'character' ? 'character' : (entry as WikiEntry).kind,
    );
    const kindLabel = getKindLabel(entryType, entry);
    const subLabel = getSubLabel(entry, entryType, chapterRole);

    return (
        <div className="rounded-lg bg-neutral-bg/50">
            <button
                type="button"
                onClick={onToggleExpand}
                className="flex w-full items-center gap-2.5 p-2.5 text-left"
            >
                <WikiAvatar name={entry.name} tab={tab} size="sm" />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium text-ink">
                        {entry.name}
                    </p>
                    <p className="text-[11px] text-ink-faint">
                        {kindLabel}
                        {subLabel && ` · ${subLabel}`}
                    </p>
                </div>
                {!isConnected && onDismiss && (
                    <span
                        role="button"
                        tabIndex={0}
                        onClick={(e) => {
                            e.stopPropagation();
                            onDismiss();
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.stopPropagation();
                                onDismiss();
                            }
                        }}
                        className="shrink-0 rounded p-0.5 text-ink-faint hover:text-ink"
                    >
                        <X size={12} />
                    </span>
                )}
                {isExpanded ? (
                    <ChevronUp size={14} className="shrink-0 text-ink-faint" />
                ) : (
                    <ChevronDown
                        size={14}
                        className="shrink-0 text-ink-faint"
                    />
                )}
            </button>

            {isExpanded && (
                <ExpandedBody
                    entry={entry}
                    entryType={entryType}
                    isConnected={isConnected}
                    onConnect={onConnect}
                    onDisconnect={onDisconnect}
                    onRoleChange={onRoleChange}
                    onUpdate={onUpdate}
                    chapterRole={chapterRole}
                    wikiUrl={wikiUrl}
                    onDismiss={onDismiss}
                />
            )}
        </div>
    );
}

function ExpandedBody({
    entry,
    entryType,
    isConnected,
    onConnect,
    onDisconnect,
    onRoleChange,
    onUpdate,
    chapterRole,
    wikiUrl,
    onDismiss,
}: {
    entry: Character | WikiEntry;
    entryType: 'character' | 'wiki_entry';
    isConnected: boolean;
    onConnect?: (role?: string) => void;
    onDisconnect?: () => void;
    onRoleChange?: (role: string) => void;
    onUpdate: (data: Record<string, unknown>) => void;
    chapterRole?: string;
    wikiUrl: string;
    onDismiss?: () => void;
}) {
    const { t } = useTranslation('wiki-panel');
    const isCharacter = entryType === 'character';
    const character = isCharacter ? (entry as Character) : null;

    return (
        <div className="flex flex-col gap-3 px-3 pb-3">
            <div className="flex justify-end">
                <OverflowMenu
                    isConnected={isConnected}
                    isCharacter={isCharacter}
                    onConnect={onConnect}
                    onDisconnect={onDisconnect}
                    onRoleChange={onRoleChange}
                    onDismiss={onDismiss}
                    chapterRole={chapterRole}
                    wikiUrl={wikiUrl}
                />
            </div>

            {isCharacter && (
                <AliasesSection
                    aliases={character?.aliases ?? []}
                    onUpdate={(aliases) => onUpdate({ aliases })}
                />
            )}

            <EditableDescription
                label={t('description')}
                value={entry.description ?? ''}
                onChange={(value) => onUpdate({ description: value })}
            />

            {entry.ai_description && (
                <div className="flex flex-col gap-1.5">
                    <div className="flex items-center gap-1">
                        <Sparkles size={10} className="text-ink-faint" />
                        <SectionLabel variant="section">
                            {t('aiDescription')}
                        </SectionLabel>
                    </div>
                    <p className="text-[12px] leading-relaxed text-ink-muted italic">
                        {entry.ai_description}
                    </p>
                </div>
            )}
        </div>
    );
}

function AliasesSection({
    aliases: initialAliases,
    onUpdate,
}: {
    aliases: string[];
    onUpdate: (aliases: string[]) => void;
}) {
    const { t } = useTranslation('wiki-panel');
    const [localAliases, setLocalAliases] = useState(initialAliases);
    const [isAdding, setIsAdding] = useState(false);
    const [newAlias, setNewAlias] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setLocalAliases(initialAliases);
    }, [initialAliases]);

    useEffect(() => {
        if (isAdding) inputRef.current?.focus();
    }, [isAdding]);

    const handleAdd = useCallback(() => {
        const trimmed = newAlias.trim();
        if (trimmed && !localAliases.includes(trimmed)) {
            const next = [...localAliases, trimmed];
            setLocalAliases(next);
            onUpdate(next);
        }
        setNewAlias('');
        setIsAdding(false);
    }, [newAlias, localAliases, onUpdate]);

    const handleRemove = useCallback(
        (alias: string) => {
            const next = localAliases.filter((a) => a !== alias);
            setLocalAliases(next);
            onUpdate(next);
        },
        [localAliases, onUpdate],
    );

    return (
        <div className="flex flex-col gap-1.5">
            <SectionLabel variant="section">{t('aliases')}</SectionLabel>
            <div className="flex flex-wrap items-center gap-1.5">
                {localAliases.map((alias) => (
                    <Badge
                        key={alias}
                        variant="secondary"
                        className="gap-1 pr-1"
                    >
                        {alias}
                        <button
                            type="button"
                            onClick={() => handleRemove(alias)}
                            className="rounded-full p-0.5 hover:bg-ink/10"
                        >
                            <X size={8} />
                        </button>
                    </Badge>
                ))}
                {isAdding ? (
                    <input
                        ref={inputRef}
                        value={newAlias}
                        onChange={(e) => setNewAlias(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') handleAdd();
                            if (e.key === 'Escape') {
                                setIsAdding(false);
                                setNewAlias('');
                            }
                        }}
                        onBlur={handleAdd}
                        className="w-16 rounded border border-border bg-transparent px-1.5 py-0.5 text-[11px] text-ink focus:ring-1 focus:ring-ink focus:outline-none"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setIsAdding(true)}
                        className="inline-flex items-center gap-1 rounded-full border border-dashed border-border px-2 py-0.5 text-[11px] text-ink-faint transition-colors hover:text-ink"
                    >
                        <Plus size={10} />
                        {t('addAlias')}
                    </button>
                )}
            </div>
        </div>
    );
}

function EditableDescription({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const [localValue, setLocalValue] = useState(value);
    const debouncedOnChange = useDebouncedCallback(onChange, 1500);

    useEffect(() => {
        setLocalValue(value);
    }, [value]);

    const handleChange = useCallback(
        (newValue: string) => {
            setLocalValue(newValue);
            debouncedOnChange(newValue);
        },
        [debouncedOnChange],
    );

    return (
        <div className="flex flex-col gap-1.5">
            <SectionLabel variant="section">{label}</SectionLabel>
            <textarea
                value={localValue}
                onChange={(e) => handleChange(e.target.value)}
                rows={3}
                className={cn(
                    'w-full resize-none rounded-md border border-transparent bg-transparent text-[12px] leading-relaxed text-ink',
                    'placeholder:text-ink-faint focus:border-border focus:outline-none',
                )}
                placeholder="Add a description..."
            />
        </div>
    );
}

function OverflowMenu({
    isConnected,
    isCharacter,
    onConnect,
    onDisconnect,
    onRoleChange,
    onDismiss,
    chapterRole,
    wikiUrl,
}: {
    isConnected: boolean;
    isCharacter: boolean;
    onConnect?: (role?: string) => void;
    onDisconnect?: () => void;
    onRoleChange?: (role: string) => void;
    onDismiss?: () => void;
    chapterRole?: string;
    wikiUrl: string;
}) {
    const { t } = useTranslation('wiki-panel');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="rounded p-1 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink">
                <Ellipsis size={14} />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4}>
                {!isConnected && onConnect && (
                    <>
                        {isCharacter ? (
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <UserCog size={14} className="mr-1" />
                                    {t('connectToChapter')}
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent>
                                    {ROLES.map((role) => (
                                        <DropdownMenuItem
                                            key={role}
                                            onSelect={() => onConnect(role)}
                                        >
                                            {t(`roles.${role}`)}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                        ) : (
                            <DropdownMenuItem onSelect={() => onConnect()}>
                                <UserCog size={14} />
                                {t('connectToChapter')}
                            </DropdownMenuItem>
                        )}
                    </>
                )}

                {isConnected && isCharacter && onRoleChange && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <UserCog size={14} className="mr-1" />
                            {t('changeRole')}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            {ROLES.map((role) => (
                                <DropdownMenuItem
                                    key={role}
                                    onSelect={() => onRoleChange(role)}
                                    className={cn(
                                        chapterRole === role && 'font-medium',
                                    )}
                                >
                                    {t(`roles.${role}`)}
                                    {chapterRole === role && ' ✓'}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                )}

                {isConnected && onDisconnect && (
                    <DropdownMenuItem onSelect={onDisconnect}>
                        <Link2Off size={14} />
                        {t('disconnectFromChapter')}
                    </DropdownMenuItem>
                )}

                {!isConnected && onDismiss && (
                    <DropdownMenuItem onSelect={onDismiss}>
                        <X size={14} />
                        {t('dismiss')}
                    </DropdownMenuItem>
                )}

                <DropdownMenuSeparator />

                <DropdownMenuItem
                    onSelect={() => {
                        window.location.href = wikiUrl;
                    }}
                >
                    <ExternalLink size={14} />
                    {t('openInWiki')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

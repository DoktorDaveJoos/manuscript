import { ChevronDown, ChevronUp, Plus, Sparkles, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import SectionLabel from '@/components/ui/SectionLabel';
import DescriptionBlock from '@/components/wiki/DescriptionBlock';
import WikiAvatar from '@/components/wiki/WikiAvatar';
import type { WikiTab } from '@/components/wiki/WikiTabBar';
import type { Character, WikiEntry } from '@/types/models';
import PanelCardMenu from './PanelCardMenu';
import type { ChapterRole } from './PanelCardMenu';

type WikiPanelCardProps = {
    entry: Character | WikiEntry;
    entryType: 'character' | 'wiki_entry';
    isConnected: boolean;
    isExpanded: boolean;
    onToggleExpand: () => void;
    onDismiss?: () => void;
    onConnect?: () => void;
    onDisconnect?: () => void;
    onChangeRole?: (role: ChapterRole) => void;
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

export default function WikiPanelCard({
    entry,
    entryType,
    isConnected,
    isExpanded,
    onToggleExpand,
    onDismiss,
    onConnect,
    onDisconnect,
    onChangeRole,
    chapterRole,
    wikiUrl,
}: WikiPanelCardProps) {
    const { t } = useTranslation('wiki-panel');
    const { t: tWiki } = useTranslation('wiki');
    const tab =
        entryType === 'character'
            ? 'characters'
            : kindToTab((entry as WikiEntry).kind);
    const kindLabel =
        entryType === 'character'
            ? tWiki('dropdown.character')
            : tWiki(`dropdown.${(entry as WikiEntry).kind}`, {
                  defaultValue: capitalize((entry as WikiEntry).kind),
              });
    const subLabel = getSubLabel(entry, entryType, chapterRole);

    return (
        <div className="flex flex-col rounded-lg bg-neutral-bg/50">
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
                {isConnected && (
                    <PanelCardMenu
                        openUrl={wikiUrl}
                        openLabel={t('openInWiki')}
                        disconnectLabel={t('disconnectFromChapter')}
                        onDisconnect={onDisconnect}
                        currentRole={
                            entryType === 'character' &&
                            (chapterRole === 'protagonist' ||
                                chapterRole === 'supporting' ||
                                chapterRole === 'mentioned')
                                ? (chapterRole as ChapterRole)
                                : undefined
                        }
                        onChangeRole={
                            entryType === 'character' ? onChangeRole : undefined
                        }
                        roleLabels={
                            entryType === 'character'
                                ? {
                                      protagonist: t('role.protagonist'),
                                      supporting: t('role.supporting'),
                                      mentioned: t('role.mentioned'),
                                  }
                                : undefined
                        }
                        setRoleLabel={
                            entryType === 'character' ? t('setRole') : undefined
                        }
                    />
                )}
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
                    isConnected={isConnected}
                    onConnect={onConnect}
                />
            )}
        </div>
    );
}

function ExpandedBody({
    entry,
    isConnected,
    onConnect,
}: {
    entry: Character | WikiEntry;
    isConnected: boolean;
    onConnect?: () => void;
}) {
    const { t } = useTranslation('wiki-panel');
    const description = entry.description?.trim();

    return (
        <div className="flex flex-col gap-2.5 px-3 pb-3">
            {description ? (
                <DescriptionBlock
                    text={description}
                    className="text-[12px] leading-relaxed text-ink-muted"
                />
            ) : (
                <p className="text-[12px] text-ink-faint italic">
                    {t('noDescription')}
                </p>
            )}

            {entry.ai_description && (
                <div className="flex flex-col gap-1.5">
                    <div className="flex items-center gap-1">
                        <Sparkles size={10} className="text-ink-faint" />
                        <SectionLabel variant="section">
                            {t('aiDescription')}
                        </SectionLabel>
                    </div>
                    <DescriptionBlock
                        text={entry.ai_description}
                        className="text-[12px] leading-relaxed text-ink-muted italic"
                    />
                </div>
            )}

            {!isConnected && onConnect && (
                <button
                    type="button"
                    onClick={onConnect}
                    className="flex items-center justify-center gap-1.5 self-start rounded-md bg-ink px-2.5 py-1 text-[12px] font-medium text-surface transition-colors hover:bg-ink-muted"
                >
                    <Plus className="size-3" />
                    {t('connectToChapter')}
                </button>
            )}
        </div>
    );
}

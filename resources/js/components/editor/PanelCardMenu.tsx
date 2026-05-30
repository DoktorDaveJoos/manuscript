import { router } from '@inertiajs/react';
import { Check, Ellipsis, ExternalLink, Link2Off, UserCog } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

export type ChapterRole = 'protagonist' | 'supporting' | 'mentioned';

type Props = {
    openUrl: string;
    openLabel: string;
    disconnectLabel: string;
    onDisconnect?: () => void;
    currentRole?: ChapterRole;
    onChangeRole?: (role: ChapterRole) => void;
    roleLabels?: { protagonist: string; supporting: string; mentioned: string };
    setRoleLabel?: string;
};

const ROLE_ORDER: ChapterRole[] = ['protagonist', 'supporting', 'mentioned'];

export default function PanelCardMenu({
    openUrl,
    openLabel,
    disconnectLabel,
    onDisconnect,
    currentRole,
    onChangeRole,
    roleLabels,
    setRoleLabel,
}: Props) {
    const { t } = useTranslation('editor');
    const showRoleSub = Boolean(onChangeRole && roleLabels && setRoleLabel);
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <span
                    role="button"
                    tabIndex={0}
                    aria-label={t('textActions.moreActions')}
                    onClick={(e) => e.stopPropagation()}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.stopPropagation();
                        }
                    }}
                    className="shrink-0 rounded-md p-1 text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <Ellipsis className="size-3.5" />
                </span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4}>
                <DropdownMenuItem onClick={() => router.visit(openUrl)}>
                    <ExternalLink className="size-3.5" />
                    {openLabel}
                </DropdownMenuItem>
                {showRoleSub && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger>
                            <span className="flex items-center gap-2">
                                <UserCog className="size-3.5" />
                                {setRoleLabel}
                            </span>
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            {ROLE_ORDER.map((role) => (
                                <DropdownMenuItem
                                    key={role}
                                    onClick={() => onChangeRole?.(role)}
                                >
                                    <Check
                                        className={
                                            'size-3.5 ' +
                                            (currentRole === role
                                                ? 'opacity-100'
                                                : 'opacity-0')
                                        }
                                    />
                                    {roleLabels?.[role]}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                )}
                {onDisconnect && (
                    <DropdownMenuItem variant="danger" onClick={onDisconnect}>
                        <Link2Off className="size-3.5" />
                        {disconnectLabel}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

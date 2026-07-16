import { router } from '@inertiajs/react';
import { Check, Ellipsis, ExternalLink, Link2Off, UserCog } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
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
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('textActions.moreActions')}
                    onClick={(e) => e.stopPropagation()}
                    className="size-6 shrink-0 text-ink-faint"
                >
                    <Ellipsis className="size-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4}>
                <DropdownMenuGroup>
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
                                <DropdownMenuGroup>
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
                                </DropdownMenuGroup>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                    )}
                    {onDisconnect && (
                        <DropdownMenuItem
                            variant="danger"
                            onClick={onDisconnect}
                        >
                            <Link2Off className="size-3.5" />
                            {disconnectLabel}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

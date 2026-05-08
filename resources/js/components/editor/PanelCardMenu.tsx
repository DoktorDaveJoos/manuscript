import { router } from '@inertiajs/react';
import { Ellipsis, ExternalLink, Link2Off } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

type Props = {
    openUrl: string;
    openLabel: string;
    disconnectLabel: string;
    onDisconnect?: () => void;
};

export default function PanelCardMenu({
    openUrl,
    openLabel,
    disconnectLabel,
    onDisconnect,
}: Props) {
    const { t } = useTranslation('editor');
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

import { EllipsisVertical } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';
import { useAiFeatures } from '@/hooks/useAiFeatures';

export default function TextActionsDropdown({
    onNormalizeClick,
    onBeautifyClick,
    isBeautifying = false,
}: {
    onNormalizeClick: () => void;
    onBeautifyClick: () => void;
    isBeautifying?: boolean;
}) {
    const { visible, usable } = useAiFeatures();
    const { t } = useTranslation('editor');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    title={t('textActions.moreActions')}
                    aria-label={t('textActions.moreActions')}
                    className="size-7 text-ink-muted"
                >
                    <EllipsisVertical size={14} strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[220px]">
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        className="flex-col items-start gap-0.5"
                        onSelect={onNormalizeClick}
                    >
                        <span className="text-xs font-medium text-ink">
                            {t('textActions.normalize')}
                        </span>
                        <span className="text-[11px] text-ink-faint">
                            {t('textActions.normalizeDescription')}
                        </span>
                    </DropdownMenuItem>

                    {visible && (
                        <DropdownMenuItem
                            className="flex-col items-start gap-0.5"
                            disabled={!usable || isBeautifying}
                            onSelect={onBeautifyClick}
                        >
                            <span className="flex items-center gap-1.5 text-xs font-medium text-ink">
                                {t('textActions.beautify')}
                                <span className="rounded bg-ink-faint/10 px-1 py-0.5 text-[11px] font-medium text-ink-faint">
                                    AI
                                </span>
                            </span>
                            <span className="text-[11px] text-ink-faint">
                                {isBeautifying
                                    ? t('textActions.processing')
                                    : t('textActions.beautifyDescription')}
                            </span>
                        </DropdownMenuItem>
                    )}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

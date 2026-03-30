import { EllipsisVertical, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
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
    const { visible, usable, licensed } = useAiFeatures();
    const { t } = useTranslation('editor');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    title={t('textActions.moreActions')}
                    className="flex h-7 w-7 items-center justify-center rounded-md text-xs text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <EllipsisVertical size={14} strokeWidth={2.5} />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[220px]">
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
                            {!licensed && (
                                <span className="flex items-center gap-0.5 rounded bg-ink-faint/10 px-1 py-0.5 text-[11px] font-medium text-ink-faint">
                                    <Lock size={12} />
                                    PRO
                                </span>
                            )}
                        </span>
                        <span className="text-[11px] text-ink-faint">
                            {isBeautifying
                                ? t('textActions.processing')
                                : t('textActions.beautifyDescription')}
                        </span>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

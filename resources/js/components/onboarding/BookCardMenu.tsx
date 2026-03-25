import { Copy, EllipsisVertical, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

export default function BookCardMenu({
    onRename,
    onDuplicate,
    onDelete,
}: {
    onRename: () => void;
    onDuplicate?: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation('onboarding');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }}
                    className="flex h-7 w-7 items-center justify-center rounded-md text-ink-muted transition-colors hover:bg-neutral-bg hover:text-ink"
                >
                    <EllipsisVertical size={14} strokeWidth={2.5} />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[180px]">
                <DropdownMenuItem onSelect={onRename}>
                    <Pencil size={14} className="shrink-0 text-ink-muted" />
                    {t('bookCardMenu.rename')}
                </DropdownMenuItem>

                {onDuplicate && (
                    <DropdownMenuItem onSelect={onDuplicate}>
                        <Copy size={14} className="shrink-0 text-ink-muted" />
                        {t('bookCardMenu.duplicate')}
                    </DropdownMenuItem>
                )}

                <DropdownMenuSeparator />

                <DropdownMenuItem variant="danger" onSelect={onDelete}>
                    <Trash2 size={14} />
                    {t('bookCardMenu.delete')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

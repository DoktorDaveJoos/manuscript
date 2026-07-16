import { Copy, EllipsisVertical, Pencil, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/DropdownMenu';

export default function BookCardMenu({
    onRename,
    onDuplicate,
    onDelete,
    onOpenChange,
}: {
    onRename: () => void;
    onDuplicate?: () => void;
    onDelete: () => void;
    onOpenChange?: (open: boolean) => void;
}) {
    const { t } = useTranslation('onboarding');

    return (
        <DropdownMenu onOpenChange={onOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }}
                    aria-label={t('bookCardMenu.actions')}
                    className="size-7 text-ink-muted"
                >
                    <EllipsisVertical size={14} strokeWidth={2.5} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-[180px]">
                <DropdownMenuGroup>
                    <DropdownMenuItem onSelect={onRename}>
                        <Pencil size={14} className="shrink-0 text-ink-muted" />
                        {t('bookCardMenu.rename')}
                    </DropdownMenuItem>

                    {onDuplicate && (
                        <DropdownMenuItem onSelect={onDuplicate}>
                            <Copy
                                size={14}
                                className="shrink-0 text-ink-muted"
                            />
                            {t('bookCardMenu.duplicate')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuGroup>

                <DropdownMenuSeparator />

                <DropdownMenuGroup>
                    <DropdownMenuItem variant="danger" onSelect={onDelete}>
                        <Trash2 size={14} />
                        {t('bookCardMenu.delete')}
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '@/components/ui/Button';
import Dialog from '@/components/ui/Dialog';
import FormField from '@/components/ui/FormField';
import Input from '@/components/ui/Input';

export default function RenameDialog({
    title,
    label,
    value,
    onSubmit,
    onClose,
}: {
    title: string;
    label: string;
    value: string;
    onSubmit: (newValue: string) => Promise<void>;
    onClose: () => void;
}) {
    const { t } = useTranslation('editor');
    const [name, setName] = useState(value);
    const [processing, setProcessing] = useState(false);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        const trimmed = name.trim();
        if (!trimmed || trimmed === value) {
            onClose();
            return;
        }
        setProcessing(true);
        try {
            await onSubmit(trimmed);
            onClose();
        } finally {
            setProcessing(false);
        }
    }

    return (
        <Dialog onClose={onClose} backdrop="none" width={360} className="gap-5">
            <form onSubmit={handleSubmit} className="contents">
                <h2 className="text-base font-medium text-ink">{title}</h2>

                <FormField label={label}>
                    <Input
                        variant="dialog"
                        type="text"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoFocus
                    />
                </FormField>

                <div className="flex items-center justify-end gap-3">
                    <Button variant="ghost" type="button" onClick={onClose}>
                        {t('renameDialog.cancel')}
                    </Button>
                    <Button
                        variant="primary"
                        type="submit"
                        disabled={processing}
                    >
                        {t('renameDialog.save')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}

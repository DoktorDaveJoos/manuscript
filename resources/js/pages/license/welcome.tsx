import { Head, router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { activate } from '@/actions/App/Http/Controllers/LicenseController';
import { Alert, AlertDescription } from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
import { jsonFetchHeaders } from '@/lib/utils';

export default function LicenseWelcome() {
    const { t } = useTranslation('settings');
    const [key, setKey] = useState('');
    const [activating, setActivating] = useState(false);
    const [error, setError] = useState('');

    const handleActivate = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setActivating(true);
            setError('');

            fetch(activate.url(), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ license_key: key.trim() }),
            })
                .then(async (res) => {
                    const json = await res.json();
                    if (!res.ok) {
                        setError(
                            res.status === 503
                                ? t('license.error.network')
                                : json.message || t('license.error.invalid'),
                        );
                        return;
                    }
                    router.visit('/');
                })
                .catch(() => setError(t('license.error.network')))
                .finally(() => setActivating(false));
        },
        [key, t],
    );

    return (
        <>
            <Head title={t('welcome.title')} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-surface px-6">
                <div className="flex w-full max-w-[440px] flex-col items-center gap-10">
                    <div className="flex flex-col items-center gap-4">
                        <span className="text-[11px] font-semibold tracking-[0.08em] text-ink-muted uppercase">
                            Manuscript
                        </span>
                        <h1 className="font-serif text-[32px] leading-10 font-semibold tracking-[-0.01em] text-ink">
                            {t('welcome.title')}
                        </h1>
                        <p className="max-w-md text-center text-sm leading-6 text-ink-muted">
                            {t('welcome.description')}
                        </p>
                    </div>

                    <form
                        onSubmit={handleActivate}
                        className="flex w-full flex-col gap-3"
                    >
                        <Input
                            type="text"
                            value={key}
                            onChange={(e) => setKey(e.target.value)}
                            placeholder={t('license.keyPlaceholder')}
                            className="text-center font-mono"
                            autoFocus
                        />
                        {error && (
                            <Alert variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                        <Button
                            variant="primary"
                            size="lg"
                            type="submit"
                            disabled={activating || !key.trim()}
                            className="w-full"
                        >
                            {activating
                                ? t('license.activating')
                                : t('license.activate')}
                        </Button>
                    </form>

                    <a
                        href="https://getmanuscript.app"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-[13px] text-ink-muted underline-offset-4 transition-colors hover:text-ink hover:underline"
                    >
                        {t('welcome.buyLicense')}
                    </a>
                </div>
            </div>
        </>
    );
}

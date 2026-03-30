import { router, usePage } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import {
    activate,
    deactivate,
    revalidate,
} from '@/actions/App/Http/Controllers/LicenseController';
import Button from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Input from '@/components/ui/Input';
import PageHeader from '@/components/ui/PageHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import SettingsLayout from '@/layouts/SettingsLayout';
import { jsonFetchHeaders } from '@/lib/utils';
import type { License } from '@/types/models';

interface Props {
    book?: { id: number; title: string } | null;
}

export default function LicensePage({ book }: Props) {
    const { t } = useTranslation('settings');
    const { license } = usePage<{ license: License }>().props;
    const [key, setKey] = useState('');
    const [activating, setActivating] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!license.active) return;

        const controller = new AbortController();

        fetch(revalidate.url(), {
            method: 'POST',
            headers: jsonFetchHeaders(),
            signal: controller.signal,
        }).catch(() => {
            // Silently ignore — revalidation is best-effort
        });

        return () => controller.abort();
    }, [license.active]);

    const handleActivate = useCallback(
        (e: FormEvent) => {
            e.preventDefault();
            setActivating(true);
            setError('');

            fetch(activate.url(), {
                method: 'POST',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ license_key: key }),
            })
                .then(async (res) => {
                    const json = await res.json();
                    if (!res.ok) {
                        if (res.status === 503) {
                            setError(t('license.error.network'));
                        } else {
                            setError(
                                json.message || t('license.error.invalid'),
                            );
                        }
                        return;
                    }
                    setKey('');
                    router.reload();
                })
                .catch(() => setError(t('license.error.network')))
                .finally(() => setActivating(false));
        },
        [key, t],
    );

    const handleDeactivate = useCallback(() => {
        setError('');

        fetch(deactivate.url(), {
            method: 'POST',
            headers: jsonFetchHeaders(),
        })
            .then(async (res) => {
                if (!res.ok) {
                    const json = await res.json();
                    setError(
                        res.status === 503
                            ? t('license.error.network')
                            : json.message || t('license.error.failed'),
                    );
                    return;
                }
                router.reload();
            })
            .catch(() => setError(t('license.error.network')));
    }, [t]);

    return (
        <SettingsLayout
            activeSection="license"
            book={book}
            title={t('license.title')}
        >
            <div className="flex flex-col gap-6">
                <PageHeader
                    title={t('license.title')}
                    subtitle={t('license.description')}
                />

                {license.active ? (
                    <Card className="p-6">
                        <div className="flex items-center gap-3">
                            <span className="text-status-final">●</span>
                            <span className="text-sm font-medium text-ink">
                                {t('license.active')}
                            </span>
                            <span className="text-[13px] text-ink-muted">
                                {license.masked_key}
                            </span>
                            <button
                                type="button"
                                onClick={handleDeactivate}
                                className="ml-auto text-[13px] text-accent transition-colors hover:opacity-80"
                            >
                                {t('license.deactivate')}
                            </button>
                        </div>
                        {error && (
                            <span className="mt-2 block text-[12px] text-danger">
                                {error}
                            </span>
                        )}
                    </Card>
                ) : (
                    <Card className="p-6">
                        <h2 className="text-sm font-medium text-ink">
                            {t('license.formTitle')}
                        </h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            {t('license.formDescription')}
                        </p>
                        <form onSubmit={handleActivate} className="mt-4">
                            <SectionLabel
                                variant="section"
                                className="mb-1.5 block"
                            >
                                {t('license.keyLabel')}
                            </SectionLabel>
                            <div className="flex items-start gap-3">
                                <div className="flex flex-1 flex-col gap-1">
                                    <Input
                                        type="text"
                                        value={key}
                                        onChange={(e) => setKey(e.target.value)}
                                        placeholder={t(
                                            'license.keyPlaceholder',
                                        )}
                                        className="font-mono"
                                    />
                                    {error && (
                                        <span className="text-[12px] text-danger">
                                            {error}
                                        </span>
                                    )}
                                </div>
                                <Button
                                    variant="accent"
                                    type="submit"
                                    disabled={activating || !key}
                                    className="h-9"
                                >
                                    {activating
                                        ? t('license.activating')
                                        : t('license.activate')}
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
            </div>
        </SettingsLayout>
    );
}

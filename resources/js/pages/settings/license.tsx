import { activate, deactivate } from '@/actions/App/Http/Controllers/LicenseController';
import SettingsLayout from '@/layouts/SettingsLayout';
import { getXsrfToken } from '@/lib/csrf';
import type { License } from '@/types/models';
import { router, usePage } from '@inertiajs/react';
import { useState, useCallback, type FormEvent } from 'react';

interface Props {
    book?: { id: number; title: string } | null;
}

export default function LicensePage({ book }: Props) {
    const { license } = usePage<{ license: License }>().props;
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
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ license_key: key }),
            })
                .then(async (res) => {
                    const json = await res.json();
                    if (!res.ok) {
                        setError(json.message || 'Invalid license key.');
                        return;
                    }
                    setKey('');
                    router.reload();
                })
                .catch(() => setError('Failed to activate license.'))
                .finally(() => setActivating(false));
        },
        [key],
    );

    const handleDeactivate = useCallback(() => {
        fetch(deactivate.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                Accept: 'application/json',
            },
        }).then(() => router.reload());
    }, []);

    return (
        <SettingsLayout activeSection="license" book={book} title="License">
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">License</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        Manage your Manuscript license.
                    </p>
                </div>

                {license.active ? (
                    <div className="rounded-lg border border-border bg-surface-card p-6">
                        <div className="flex items-center gap-3">
                            <span className="text-status-final">●</span>
                            <span className="text-[15px] font-medium text-ink">License active</span>
                            <span className="text-[13px] text-ink-muted">{license.masked_key}</span>
                            <button
                                type="button"
                                onClick={handleDeactivate}
                                className="ml-auto text-[13px] text-accent transition-colors hover:opacity-80"
                            >
                                Deactivate
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="rounded-lg border border-border bg-surface-card p-6">
                        <h2 className="text-[15px] font-medium text-ink">Manuscript License</h2>
                        <p className="mt-1 text-[13px] text-ink-muted">
                            Enter your license key to unlock AI features, Canvas, and more.
                        </p>
                        <form onSubmit={handleActivate} className="mt-4">
                            <span className="mb-1.5 block text-[11px] font-medium uppercase tracking-[0.08em] text-ink-faint">
                                License Key
                            </span>
                            <div className="flex items-start gap-3">
                                <div className="flex flex-1 flex-col gap-1">
                                    <input
                                        type="text"
                                        value={key}
                                        onChange={(e) => setKey(e.target.value)}
                                        placeholder="MANU.XXXXXXXX.…"
                                        className="h-9 rounded-md border border-border bg-surface px-3 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none"
                                    />
                                    {error && <span className="text-[12px] text-danger">{error}</span>}
                                </div>
                                <button
                                    type="submit"
                                    disabled={activating || !key}
                                    className="h-9 rounded-md bg-accent px-4 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                                >
                                    {activating ? 'Activating...' : 'Activate'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}

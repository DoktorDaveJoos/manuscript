import { usePage } from '@inertiajs/react';
import { HardDrive } from 'lucide-react';
import { useState } from 'react';
import Dialog from '@/components/ui/Dialog';

interface RepairDetails {
    recovered: string[];
    failed: string[];
}

export default function DatabaseRepairedDialog() {
    const { repair_details } = usePage<{
        repair_details?: RepairDetails;
    }>().props;

    const [dismissed, setDismissed] = useState(false);

    if (dismissed || !repair_details) {
        return null;
    }

    const { recovered, failed } = repair_details;
    const total = recovered.length + failed.length;
    const allRecovered = failed.length === 0 && recovered.length > 0;

    return (
        <Dialog
            onClose={() => setDismissed(true)}
            width={440}
            backdrop="dark"
            title="Database Repaired"
            className="overflow-hidden rounded-2xl p-0 shadow-[0_16px_48px_-4px_rgba(0,0,0,0.15),0_4px_12px_rgba(0,0,0,0.05)]"
        >
            {/* Header */}
            <div className="flex flex-col items-center gap-4 px-10 pt-8">
                <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-b from-accent-light to-surface-warm">
                    <HardDrive className="size-6 text-accent" />
                </div>
                <h2 className="text-xl font-semibold tracking-tight text-ink">
                    Database Repaired
                </h2>
                <p className="text-center text-sm leading-relaxed text-ink-muted">
                    {allRecovered
                        ? 'Your database was automatically repaired and all your data has been restored.'
                        : 'Your database was automatically repaired. Some data could be recovered from the backup.'}
                </p>
            </div>

            {/* Recovery summary */}
            {total > 0 && (
                <div className="flex flex-col gap-3 px-10 pt-6">
                    <span className="text-[11px] font-medium tracking-wide text-ink-faint uppercase">
                        Recovery Summary
                    </span>

                    {recovered.length > 0 && (
                        <p className="text-[13px] text-ink-soft">
                            <span className="font-medium text-ink">
                                {recovered.length}
                            </span>{' '}
                            of {total} tables recovered successfully.
                        </p>
                    )}

                    {failed.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <p className="text-[13px] text-ink-muted">
                                Could not recover:
                            </p>
                            <p className="text-[13px] text-ink-faint">
                                {failed.join(', ')}
                            </p>
                        </div>
                    )}
                </div>
            )}

            {/* Divider */}
            <div className="mt-6 h-px bg-border-light" />

            {/* Footer */}
            <div className="flex flex-col items-center px-10 pt-6 pb-8">
                <button
                    type="button"
                    onClick={() => setDismissed(true)}
                    className="flex h-11 w-full items-center justify-center rounded-md bg-ink text-sm font-semibold text-surface shadow-[0_1px_3px_rgba(0,0,0,0.1)]"
                >
                    Continue
                </button>
            </div>
        </Dialog>
    );
}

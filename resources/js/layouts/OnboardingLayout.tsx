import { Head } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function OnboardingLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen flex-col bg-surface">
                <header className="flex items-center justify-between px-10 py-4">
                    <span className="text-[13px] font-semibold uppercase tracking-[0.08em] text-ink">
                        Manuscript
                    </span>
                    <span className="cursor-pointer text-[13px] text-ink-muted">Settings</span>
                </header>

                <main className="flex flex-1 flex-col">{children}</main>
            </div>
        </>
    );
}

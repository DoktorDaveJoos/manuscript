import SettingsLayout from '@/layouts/SettingsLayout';

interface Props {
    version: string;
    book?: { id: number; title: string } | null;
}

export default function About({ version, book }: Props) {
    return (
        <SettingsLayout activeSection="about" book={book} title="About">
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-[22px] font-semibold tracking-[-0.01em] text-ink">About</h1>
                    <p className="mt-1 text-[14px] text-ink-muted">
                        Application information.
                    </p>
                </div>

                <div className="rounded-lg border border-border bg-white p-6">
                    <div className="flex flex-col gap-4">
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Application</span>
                            <p className="mt-0.5 text-[15px] font-medium text-ink">Manuscript</p>
                        </div>
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Version</span>
                            <p className="mt-0.5 text-[15px] text-ink">{version}</p>
                        </div>
                        <div>
                            <span className="text-[13px] font-medium text-ink-muted">Description</span>
                            <p className="mt-0.5 text-[14px] leading-relaxed text-ink-muted">
                                A desktop application for authors to write, organize, and polish manuscripts with AI assistance.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    );
}

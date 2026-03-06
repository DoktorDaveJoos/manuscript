import type { StoryBible } from '@/types/models';
import { useState } from 'react';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">{title}</span>
            {children}
        </div>
    );
}

function renderItem(item: unknown): string {
    if (typeof item === 'string') return item;
    if (typeof item === 'object' && item !== null) {
        const obj = item as Record<string, unknown>;
        return obj.name?.toString() ?? obj.description?.toString() ?? obj.title?.toString() ?? JSON.stringify(item);
    }
    return String(item);
}

export default function StoryBibleSummary({ storyBible }: { storyBible: StoryBible }) {
    const [expanded, setExpanded] = useState(false);

    const hasContent =
        (storyBible.characters?.length ?? 0) > 0 ||
        (storyBible.themes?.length ?? 0) > 0 ||
        (storyBible.plot_outline?.length ?? 0) > 0;

    if (!hasContent) return null;

    return (
        <div className="flex flex-col gap-4">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="flex items-center gap-2 text-left"
            >
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 16 16"
                    fill="none"
                    className={`text-ink-muted transition-transform ${expanded ? 'rotate-90' : ''}`}
                >
                    <path d="M6 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    Story Bible
                </span>
            </button>

            {expanded && (
                <div className="flex flex-col gap-6 pl-5">
                    {storyBible.characters && storyBible.characters.length > 0 && (
                        <Section title="Characters">
                            <div className="flex flex-col gap-1.5">
                                {storyBible.characters.map((char, i) => {
                                    const name = (char as Record<string, unknown>).name?.toString() ?? `Character ${i + 1}`;
                                    const desc = (char as Record<string, unknown>).description?.toString() ??
                                        (char as Record<string, unknown>).role?.toString() ?? '';
                                    return (
                                        <div key={i} className="text-[13px] leading-[18px]">
                                            <span className="font-medium text-ink">{name}</span>
                                            {desc && <span className="text-ink-faint"> — {desc}</span>}
                                        </div>
                                    );
                                })}
                            </div>
                        </Section>
                    )}

                    {storyBible.themes && storyBible.themes.length > 0 && (
                        <Section title="Themes">
                            <div className="flex flex-wrap gap-2">
                                {storyBible.themes.map((theme, i) => (
                                    <span
                                        key={i}
                                        className="rounded-full bg-neutral-bg px-2.5 py-0.5 text-[12px] text-ink-muted"
                                    >
                                        {renderItem(theme)}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}

                    {storyBible.plot_outline && storyBible.plot_outline.length > 0 && (
                        <Section title="Plot Outline">
                            <div className="flex flex-col gap-1">
                                {storyBible.plot_outline.map((beat, i) => (
                                    <span key={i} className="text-[13px] leading-[18px] text-ink-muted">
                                        {i + 1}. {renderItem(beat)}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}

                    {storyBible.timeline && storyBible.timeline.length > 0 && (
                        <Section title="Timeline">
                            <div className="flex flex-col gap-1">
                                {storyBible.timeline.map((event, i) => (
                                    <span key={i} className="text-[13px] leading-[18px] text-ink-muted">
                                        {renderItem(event)}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}
                </div>
            )}
        </div>
    );
}

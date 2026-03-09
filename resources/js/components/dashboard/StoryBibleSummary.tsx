import type { StoryBible } from '@/types/models';
import { CaretRight } from '@phosphor-icons/react';
import { useState } from 'react';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">{title}</span>
            {children}
        </div>
    );
}

export default function StoryBibleSummary({ storyBible }: { storyBible: StoryBible }) {
    const [expanded, setExpanded] = useState(false);

    const hasContent =
        (storyBible.themes?.length ?? 0) > 0 ||
        (storyBible.style_rules?.length ?? 0) > 0 ||
        (storyBible.genre_rules?.length ?? 0) > 0 ||
        (storyBible.timeline?.length ?? 0) > 0;

    if (!hasContent) return null;

    return (
        <div className="flex flex-col gap-4">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="flex items-center gap-2 text-left"
            >
                <CaretRight
                    size={12}
                    weight="bold"
                    className={`text-ink-muted transition-transform ${expanded ? 'rotate-90' : ''}`}
                />
                <span className="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-muted">
                    Story Bible
                </span>
            </button>

            {expanded && (
                <div className="flex flex-col gap-6 pl-5">
                    {storyBible.themes && storyBible.themes.length > 0 && (
                        <Section title="Themes">
                            <div className="flex flex-wrap gap-2">
                                {storyBible.themes.map((theme, i) => (
                                    <span
                                        key={i}
                                        className="rounded-full bg-neutral-bg px-2.5 py-0.5 text-[12px] text-ink-muted"
                                    >
                                        {theme}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}

                    {storyBible.style_rules && storyBible.style_rules.length > 0 && (
                        <Section title="Style Rules">
                            <div className="flex flex-col gap-1">
                                {storyBible.style_rules.map((rule, i) => (
                                    <span key={i} className="text-[13px] leading-[18px] text-ink-muted">
                                        {rule}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}

                    {storyBible.genre_rules && storyBible.genre_rules.length > 0 && (
                        <Section title="Genre Rules">
                            <div className="flex flex-col gap-1">
                                {storyBible.genre_rules.map((rule, i) => (
                                    <span key={i} className="text-[13px] leading-[18px] text-ink-muted">
                                        {rule}
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
                                        {event}
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

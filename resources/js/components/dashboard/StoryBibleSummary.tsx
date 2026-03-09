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

const listSections = [
    { key: 'style_rules', title: 'Style Rules' },
    { key: 'genre_rules', title: 'Genre Rules' },
    { key: 'timeline', title: 'Timeline' },
] as const;

export default function StoryBibleSummary({ storyBible }: { storyBible: StoryBible }) {
    const [expanded, setExpanded] = useState(false);

    const hasContent = Object.values(storyBible).some((v) => Array.isArray(v) && v.length > 0);

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

                    {listSections.map(({ key, title }) => {
                        const items = storyBible[key];
                        if (!items || items.length === 0) return null;
                        return (
                            <Section key={key} title={title}>
                                <div className="flex flex-col gap-1">
                                    {items.map((item, i) => (
                                        <span key={i} className="text-[13px] leading-[18px] text-ink-muted">
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            </Section>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

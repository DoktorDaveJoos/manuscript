import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StoryBible } from '@/types/models';

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                {title}
            </span>
            {children}
        </div>
    );
}

export default function StoryBibleSummary({
    storyBible,
}: {
    storyBible: StoryBible;
}) {
    const { t } = useTranslation('dashboard');
    const [expanded, setExpanded] = useState(false);

    const listSections = [
        { key: 'style_rules' as const, title: t('storyBible.styleRules') },
        { key: 'genre_rules' as const, title: t('storyBible.genreRules') },
        { key: 'timeline' as const, title: t('storyBible.timeline') },
    ];

    const hasContent = Object.values(storyBible).some(
        (v) => Array.isArray(v) && v.length > 0,
    );

    if (!hasContent) return null;

    return (
        <div className="flex flex-col gap-4">
            <button
                type="button"
                onClick={() => setExpanded(!expanded)}
                className="flex items-center gap-2 text-left"
            >
                <ChevronRight
                    size={12}
                    strokeWidth={2.5}
                    className={`text-ink-muted transition-transform ${expanded ? 'rotate-90' : ''}`}
                />
                <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
                    {t('storyBible.title')}
                </span>
            </button>

            {expanded && (
                <div className="flex flex-col gap-6 pl-5">
                    {storyBible.themes && storyBible.themes.length > 0 && (
                        <Section title={t('storyBible.themes')}>
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
                                        <span
                                            key={i}
                                            className="text-[13px] leading-[18px] text-ink-muted"
                                        >
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

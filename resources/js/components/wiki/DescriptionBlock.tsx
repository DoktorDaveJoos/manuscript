import { useMemo } from 'react';
import { md } from '@/lib/markdown';

export default function DescriptionBlock({
    text,
    className = 'text-[14px] leading-relaxed text-ink',
}: {
    text: string;
    className?: string;
}) {
    const html = useMemo(() => md.render(text), [text]);

    return (
        <div
            className={`ai-chat-markdown ${className}`}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

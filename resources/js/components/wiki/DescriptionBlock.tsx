export default function DescriptionBlock({
    text,
    className = 'text-[14px] leading-relaxed text-ink',
}: {
    text: string;
    className?: string;
}) {
    return (
        <div className={`flex flex-col gap-3 ${className}`}>
            {text
                .split('\n')
                .filter(Boolean)
                .map((paragraph, i) => (
                    <p key={i}>{paragraph}</p>
                ))}
        </div>
    );
}

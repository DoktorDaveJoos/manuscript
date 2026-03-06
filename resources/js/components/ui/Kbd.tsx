const SYMBOLS = new Set(['⌘', '⇧', '⌥', '⌃', '↵']);
const ALPHA = /[A-Za-z]/;
const LOWER = /[a-z]/;

function tokenize(keys: string): string[] {
    const tokens: string[] = [];
    let i = 0;
    while (i < keys.length) {
        if (SYMBOLS.has(keys[i])) {
            tokens.push(keys[i]);
            i++;
        } else if (ALPHA.test(keys[i])) {
            let word = keys[i];
            i++;
            while (i < keys.length && LOWER.test(keys[i])) {
                word += keys[i];
                i++;
            }
            tokens.push(word);
        } else {
            tokens.push(keys[i]);
            i++;
        }
    }
    return tokens;
}

export default function Kbd({ keys }: { keys: string }) {
    const tokens = tokenize(keys);
    return (
        <span className="inline-flex items-center gap-0.5">
            {tokens.map((token, i) => (
                <kbd
                    key={i}
                    className="inline-flex min-w-[20px] items-center justify-center rounded-[4px] border border-border bg-kbd-bg px-1 font-sans text-[10px] leading-[18px] text-ink-muted"
                >
                    {token}
                </kbd>
            ))}
        </span>
    );
}

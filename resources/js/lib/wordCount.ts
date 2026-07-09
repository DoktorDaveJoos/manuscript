// TypeScript twin of App\Support\WordCount (app/Support/WordCount.php).
// Keep both implementations in sync — the editor's live count and the
// persisted backend count must agree for the same text.
//
// Counting rule (matches Google Docs / Word): split on Unicode whitespace;
// a token counts as a word only if it contains at least one letter or
// number, so standalone punctuation ("—", "--") is ignored while numbers
// ("1999") count. CJK characters are counted individually.
const CJK_REGEX =
    /[\p{scx=Han}\p{scx=Hiragana}\p{scx=Katakana}\p{scx=Hangul}]/gu;
const HAS_LETTER_OR_NUMBER = /[\p{L}\p{N}]/u;
const WHITESPACE_RUN = /[\s\p{Z}]+/u;

export function countWords(text: string | null | undefined): number {
    if (!text || !text.trim()) return 0;

    let cjkCount = 0;
    const nonCjk = text.replace(CJK_REGEX, () => {
        cjkCount++;
        return ' ';
    });

    const latinCount = nonCjk
        .split(WHITESPACE_RUN)
        .filter((token) => HAS_LETTER_OR_NUMBER.test(token)).length;

    return cjkCount + latinCount;
}

export function countWordsInHtml(html: string | null | undefined): number {
    if (!html) return 0;
    return countWords(decodeEntities(html.replace(/<[^>]*>/g, ' ')));
}

function decodeEntities(text: string): string {
    if (!text.includes('&') || typeof document === 'undefined') return text;
    const el = document.createElement('textarea');
    el.innerHTML = text;
    return el.value;
}

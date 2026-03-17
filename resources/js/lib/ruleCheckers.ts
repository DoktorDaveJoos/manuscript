type RuleResult = { count: number; examples: string[] };

export function stripTags(html: string): string {
    return html.replace(/<[^>]*>/g, '');
}

function findMatches(
    text: string,
    pattern: RegExp,
    maxExamples = 2,
): RuleResult {
    const matches = text.match(pattern) ?? [];
    return {
        count: matches.length,
        examples: [...new Set(matches.map((m) => m.trim()))].slice(
            0,
            maxExamples,
        ),
    };
}

const FILTER_WORDS = /\b(felt|saw|heard|noticed|realized|seemed)\b/gi;

const PASSIVE_VOICE = /\b(was|were|been|being|is|are)\s+\w+ed\b/gi;

const DIALOGUE_TAGS_ADVERB = /(said|asked|replied|whispered)\s+\w+ly\b/gi;

const WORDY_PHRASES =
    /\b(in order to|the fact that|at this point in time|due to the fact|for the purpose of|in the event that)\b/gi;

const TELLING_VERBS =
    /\b(felt that|was feeling|realized that|knew that|thought that|understood that)\b/gi;

export const ruleCheckers: Record<string, (text: string) => RuleResult> = {
    filter_words: (text) => findMatches(stripTags(text), FILTER_WORDS),
    passive_voice: (text) => findMatches(stripTags(text), PASSIVE_VOICE),
    dialogue_tags: (text) => findMatches(stripTags(text), DIALOGUE_TAGS_ADVERB),
    tightening: (text) => findMatches(stripTags(text), WORDY_PHRASES),
    show_dont_tell: (text) => findMatches(stripTags(text), TELLING_VERBS),
    sentence_variety: (text) => {
        const plain = stripTags(text);
        const sentences = plain
            .split(/[.!?]+/)
            .filter((s) => s.trim().length > 0);
        if (sentences.length < 3) return { count: 0, examples: [] };
        const lengths = sentences.map((s) => s.trim().split(/\s+/).length);
        const mean = lengths.reduce((a, b) => a + b, 0) / lengths.length;
        const variance =
            lengths.reduce((sum, l) => sum + (l - mean) ** 2, 0) /
            lengths.length;
        const stdDev = Math.sqrt(variance);
        const lowVariety = stdDev < 3;
        return {
            count: lowVariety ? 1 : 0,
            examples: lowVariety
                ? [`Std dev: ${stdDev.toFixed(1)} words (low variety)`]
                : [],
        };
    },
};

export const RULE_THRESHOLDS: Record<string, number> = {
    filter_words: 5,
    passive_voice: 8,
    dialogue_tags: 3,
    tightening: 3,
    show_dont_tell: 5,
    sentence_variety: 1,
};

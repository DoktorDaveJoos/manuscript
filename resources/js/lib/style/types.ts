export type StyleCategory =
    | 'filler'
    | 'weakVerb'
    | 'filterWord'
    | 'cliche'
    | 'pattern'
    | 'repetition';

export interface StyleFinding {
    category: StyleCategory;
    from: number; // offset within the analyzed text
    to: number;
    word: string;
    /** Repetition findings: the paired occurrence. */
    partner?: { from: number; to: number; word: string };
    /** Pattern findings: which pack pattern matched. */
    patternId?: string;
}

export interface StyleStats {
    wordCount: number;
    sentenceCount: number;
    avgSentenceLength: number;
    /** Words per sentence, in document order (rhythm strip). */
    sentenceLengths: number[];
    /** 0-100 score; formula per pack. Null without enough text. */
    readability: number | null;
    /** Which formula produced the score — LIX reads low-is-easy, the others high-is-easy. */
    readabilityFormula: ReadabilityFormula | null;
    /** Share of words matching the pack's adjective suffixes. Null without pack data. */
    adjectiveRatio: number | null;
}

export type ReadabilityFormula = 'flesch' | 'amstad' | 'kandel' | 'lix';

export interface StylePack {
    version: number;
    /** High-frequency words the repetition check ignores. */
    stopwords: string[];
    /** Lists enumerate common inflections — matching is surface-form only. */
    fillers: string[];
    weakVerbs: string[];
    filterWords: string[];
    /** Multi-word phrases (clichés, pleonasms). */
    cliches: string[];
    patterns: Array<{ id: string; regex: string }>;
    adjectiveSuffixes?: string[];
    readability?: { formula: ReadabilityFormula };
}

export interface AnalyzeOptions {
    /** Category gates; a category missing from the record counts as enabled. */
    categories?: Partial<Record<StyleCategory, boolean>>;
    /** Words (lowercase) excluded from word-level findings. */
    ignoredWords?: string[];
}

/** Single prop threaded ChapterPane → WritingSurface → SceneEditor. */
export interface StyleAnalysisBridge {
    active: boolean;
    options: AnalyzeOptions;
    onSceneAnalysis: (sceneId: number, analysis: StyleAnalysis | null) => void;
    onIgnoreWord: (word: string) => void;
}

export interface StyleAnalysis {
    findings: StyleFinding[];
    stats: StyleStats;
    /** What this language's pack can flag at all (repetition is pack-independent). */
    available: Record<StyleCategory, boolean>;
}

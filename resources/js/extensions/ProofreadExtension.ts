import { Extension } from '@tiptap/core';
import {
    createProofreadPlugin,
    createSpellCheckEnabledStore,
} from 'prosemirror-proofread';
import type {
    GenerateProofreadErrorsResponse,
    CreateSuggestionBox,
} from 'prosemirror-proofread';
// @ts-expect-error — typo-js has no ESM export or type declarations
import Typo from 'typo-js';
// @ts-expect-error — write-good has no ESM export or type declarations
import writeGood from 'write-good';
import { createSuggestionBoxElement } from '@/components/editor/SuggestionPopover';
import type { ProofreadingConfig } from '@/types/models';

// ─── Typo.js singleton ──────────────────────────────────────────────

let typoInstance: InstanceType<typeof Typo> | null = null;
let typoLoading: Promise<InstanceType<typeof Typo>> | null = null;

async function getTypo(): Promise<InstanceType<typeof Typo>> {
    if (typoInstance) return typoInstance;
    if (typoLoading) return typoLoading;

    typoLoading = (async () => {
        try {
            const [affResponse, dicResponse] = await Promise.all([
                fetch('/dictionaries/en/en_US.aff'),
                fetch('/dictionaries/en/en_US.dic'),
            ]);
            if (!affResponse.ok || !dicResponse.ok) {
                throw new Error('Failed to load dictionary files');
            }
            const [affData, dicData] = await Promise.all([
                affResponse.text(),
                dicResponse.text(),
            ]);
            typoInstance = new Typo('en_US', affData, dicData);
            return typoInstance;
        } catch (e) {
            typoLoading = null; // Allow retry on next call
            throw e;
        }
    })();

    return typoLoading;
}

/**
 * Get spelling suggestions for a word. Separated from the check pass
 * so suggestions are only computed when the user clicks an underlined word.
 */
export function getSpellingSuggestions(word: string): string[] {
    if (!typoInstance) return [];
    return typoInstance.suggest(word, 5) ?? [];
}

// ─── Error generator factory ────────────────────────────────────────

function createGenerateErrors(
    config: ProofreadingConfig,
    getDictionary: () => Set<string>,
) {
    // Pre-compute which grammar checks are disabled (write-good enables all by default)
    const grammarOpts: Record<string, boolean> = {};
    for (const [key, enabled] of Object.entries(config.grammar_checks)) {
        if (!enabled) {
            grammarOpts[key] = false;
        }
    }

    return async (text: string): Promise<GenerateProofreadErrorsResponse> => {
        const matches: GenerateProofreadErrorsResponse['matches'] = [];

        if (config.spelling_enabled) {
            try {
                const typo = await getTypo();
                const dict = getDictionary();
                const wordRe = /[a-zA-Z'\u2019]+/g;
                let match: RegExpExecArray | null;
                while ((match = wordRe.exec(text)) !== null) {
                    const word = match[0];

                    if (
                        word.length <= 1 ||
                        /^[A-Z]+$/.test(word) ||
                        dict.has(word.toLowerCase())
                    ) {
                        continue;
                    }

                    if (!typo.check(word)) {
                        matches.push({
                            offset: match.index,
                            length: word.length,
                            message: `"${word}" may be misspelled`,
                            shortMessage: word,
                            type: { typeName: 'UnknownWord' },
                            replacements: [], // Computed lazily in SuggestionPopover
                        });
                    }
                }
            } catch {
                // Dictionary not loaded yet or failed — skip spelling
            }
        }

        if (config.grammar_enabled) {
            const results: Array<{
                index: number;
                offset: number;
                reason: string;
            }> = writeGood(text, grammarOpts);

            for (const result of results) {
                matches.push({
                    offset: result.index,
                    length: result.offset,
                    message: result.reason,
                    type: { typeName: 'Grammar' },
                    replacements: [],
                });
            }
        }

        return { matches };
    };
}

// ─── Extension ──────────────────────────────────────────────────────

export interface ProofreadOptions {
    config: ProofreadingConfig;
    customDictionaryRef: { current: string[] };
    onAddToDictionary?: (word: string) => void;
}

export const ProofreadExtension = Extension.create<ProofreadOptions>({
    name: 'proofread',

    addOptions() {
        return {
            config: {
                spelling_enabled: true,
                grammar_enabled: true,
                grammar_checks: {
                    illusion: true,
                    so: true,
                    thereIs: true,
                    tooWordy: true,
                    passive: false,
                    weasel: false,
                    adverb: false,
                    cliches: false,
                    eprime: false,
                },
            },
            customDictionaryRef: { current: [] },
            onAddToDictionary: undefined,
        };
    },

    addProseMirrorPlugins() {
        const { config, customDictionaryRef, onAddToDictionary } = this.options;

        const isEnabled = config.spelling_enabled || config.grammar_enabled;

        let enabledStore: ReturnType<typeof createSpellCheckEnabledStore>;
        try {
            enabledStore = createSpellCheckEnabledStore(() => isEnabled);
        } catch {
            // Outside Electron (e.g. Playwright screenshots), the store
            // factory may crash — return an empty plugin list gracefully.
            return [];
        }
        // Read dictionary from ref on each check so new words are recognized
        // without destroying the editor
        const generateErrors = createGenerateErrors(
            config,
            () =>
                new Set(
                    customDictionaryRef.current.map((w) => w.toLowerCase()),
                ),
        );

        const suggestionBox: CreateSuggestionBox = (options) => {
            // Lazily compute suggestions when the popover opens
            if (
                options.error.type === 'UnknownWord' &&
                options.error.replacements.length === 0
            ) {
                options.error.replacements = getSpellingSuggestions(
                    options.error.shortmsg,
                );
            }

            return createSuggestionBoxElement({
                ...options,
                onAddToDictionary,
            });
        };

        const plugin = createProofreadPlugin(
            1000,
            generateErrors,
            suggestionBox,
            enabledStore,
        );

        return [plugin];
    },
});

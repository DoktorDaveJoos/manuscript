import { Extension } from '@tiptap/core';
import {
    createProofreadPlugin,
    createSpellCheckEnabledStore,
} from 'prosemirror-proofread';
import type { GenerateProofreadErrorsResponse } from 'prosemirror-proofread';
// @ts-expect-error — write-good has no ESM export or type declarations
import writeGood from 'write-good';
import { createSuggestionBoxElement } from '@/components/editor/SuggestionPopover';
import type { ProofreadingConfig } from '@/types/models';

// ─── Error generator (grammar only — spelling is handled by the OS) ─

function createGenerateErrors(config: ProofreadingConfig, language: string) {
    const grammarOpts: Record<string, boolean> = {};
    for (const [key, enabled] of Object.entries(config.grammar_checks)) {
        if (!enabled) {
            grammarOpts[key] = false;
        }
    }

    // write-good only supports English
    const grammarAvailable = config.grammar_enabled && language === 'en';

    return async (text: string): Promise<GenerateProofreadErrorsResponse> => {
        const matches: GenerateProofreadErrorsResponse['matches'] = [];

        if (grammarAvailable) {
            try {
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
            } catch (e) {
                console.error('[Proofread] Grammar check error:', e);
            }
        }

        return { matches };
    };
}

// ─── Extension ──────────────────────────────────────────────────────

export interface ProofreadOptions {
    config: ProofreadingConfig;
    language: string;
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
            language: 'en',
        };
    },

    addProseMirrorPlugins() {
        const { config, language } = this.options;

        const isEnabled = config.grammar_enabled;

        if (!isEnabled) return [];

        let enabledStore: ReturnType<typeof createSpellCheckEnabledStore>;
        try {
            enabledStore = createSpellCheckEnabledStore(() => isEnabled);
        } catch (e) {
            console.error('[Proofread] enabledStore creation failed:', e);
            return [];
        }

        const generateErrors = createGenerateErrors(config, language);

        const plugin = createProofreadPlugin(
            1000,
            generateErrors,
            createSuggestionBoxElement,
            enabledStore,
        );

        return [plugin];
    },
});

import { useState, useCallback } from 'react';
import {
    updateCustomDictionary,
    updateProofreadingConfig,
} from '@/actions/App/Http/Controllers/SettingsController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ProofreadingConfig } from '@/types/models';

export function useProofreading(
    initialConfig: ProofreadingConfig,
    initialDictionary: string[],
    bookId: number,
) {
    const [config, setConfig] = useState(initialConfig);
    const [dictionary, setDictionary] = useState(initialDictionary);

    const isEnabled = config.spelling_enabled || config.grammar_enabled;

    const addToDictionary = useCallback(
        (word: string) => {
            const lower = word.toLowerCase();
            setDictionary((prev) => {
                if (prev.includes(lower)) return prev;
                return [...prev, lower].sort();
            });

            // Persist outside setState to avoid double-fire in StrictMode
            setDictionary((current) => {
                fetch(updateCustomDictionary.url({ book: bookId }), {
                    method: 'PUT',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ words: current }),
                }).catch(() => {
                    // Rollback on failure
                    setDictionary((prev) => prev.filter((w) => w !== lower));
                });
                return current;
            });
        },
        [bookId],
    );

    const removeFromDictionary = useCallback(
        (word: string) => {
            setDictionary((prev) => {
                const updated = prev.filter((w) => w !== word);

                fetch(updateCustomDictionary.url({ book: bookId }), {
                    method: 'PUT',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ words: updated }),
                }).catch(() => {
                    setDictionary(prev);
                });

                return updated;
            });
        },
        [bookId],
    );

    const toggleEnabled = useCallback(() => {
        setConfig((prev) => {
            const bothOff = !prev.spelling_enabled && !prev.grammar_enabled;
            const updated = bothOff
                ? { ...prev, spelling_enabled: true, grammar_enabled: true }
                : {
                      ...prev,
                      spelling_enabled: false,
                      grammar_enabled: false,
                  };

            fetch(updateProofreadingConfig.url(), {
                method: 'PUT',
                headers: jsonFetchHeaders(),
                body: JSON.stringify({ config: updated }),
            }).catch(() => {
                setConfig(prev);
            });

            return updated;
        });
    }, []);

    return {
        config,
        setConfig,
        dictionary,
        isEnabled,
        addToDictionary,
        removeFromDictionary,
        toggleEnabled,
    };
}

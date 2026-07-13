import { useCallback, useState } from 'react';
import {
    updateCustomDictionary,
    updateStyleIgnoredWords,
} from '@/actions/App/Http/Controllers/SettingsController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { ProofreadingConfig } from '@/types/models';

export function useProofreading(
    initialConfig: ProofreadingConfig,
    initialDictionary: string[],
    bookId: number,
    initialStyleIgnoredWords: string[] = [],
) {
    const [config, setConfig] = useState(initialConfig);
    const [dictionary, setDictionary] = useState(initialDictionary);
    const [styleIgnoredWords, setStyleIgnoredWords] = useState(
        initialStyleIgnoredWords,
    );

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

    const addStyleIgnoredWord = useCallback(
        (word: string) => {
            const lower = word.toLowerCase();
            setStyleIgnoredWords((prev) => {
                if (prev.includes(lower)) return prev;
                return [...prev, lower];
            });

            setStyleIgnoredWords((current) => {
                fetch(updateStyleIgnoredWords.url({ book: bookId }), {
                    method: 'PUT',
                    headers: jsonFetchHeaders(),
                    body: JSON.stringify({ words: current }),
                }).catch(() => {
                    setStyleIgnoredWords((prev) =>
                        prev.filter((w) => w !== lower),
                    );
                });
                return current;
            });
        },
        [bookId],
    );

    return {
        config,
        setConfig,
        dictionary,
        styleIgnoredWords,
        addToDictionary,
        removeFromDictionary,
        addStyleIgnoredWord,
    };
}

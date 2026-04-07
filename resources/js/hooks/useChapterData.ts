import { useCallback, useEffect, useRef, useState } from 'react';
import { jsonFetchHeaders } from '@/lib/utils';
import type {
    Chapter,
    Character,
    CharacterChapterPivot,
    ProofreadingConfig,
    ProsePassRule,
    Scene,
} from '@/types/models';
import { showJson } from '@/actions/App/Http/Controllers/ChapterController';

type ChapterWithRelations = Chapter & {
    characters?: (Character & { pivot: CharacterChapterPivot })[];
    scenes?: Scene[];
};

export type ChapterData = {
    chapter: ChapterWithRelations;
    versionCount: number;
    prosePassRules?: ProsePassRule[];
    proofreadingConfig?: ProofreadingConfig;
    customDictionary?: string[];
};

type UseChapterDataReturn = {
    data: ChapterData | null;
    isLoading: boolean;
    error: string | null;
    refresh: () => void;
    softRefresh: () => void;
};

export default function useChapterData(
    bookId: number,
    chapterId: number | null,
): UseChapterDataReturn {
    const [data, setData] = useState<ChapterData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const fetchChapter = useCallback(
        async (soft: boolean) => {
            if (!chapterId) return;

            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            if (!soft) {
                setData(null);
                setIsLoading(true);
                setError(null);
            }

            try {
                const response = await fetch(
                    showJson.url({ book: bookId, chapter: chapterId }),
                    {
                        headers: jsonFetchHeaders(),
                        signal: controller.signal,
                    },
                );

                if (!response.ok) throw new Error('Failed to load chapter');

                const json: ChapterData = await response.json();
                if (controller.signal.aborted) return;
                setData(json);
                setError(null);
                setIsLoading(false);
            } catch (e) {
                if (controller.signal.aborted) return;
                if (!soft) {
                    setError((e as Error).message);
                    setIsLoading(false);
                }
            }
        },
        [bookId, chapterId],
    );

    const refresh = useCallback(() => fetchChapter(false), [fetchChapter]);
    const softRefresh = useCallback(() => fetchChapter(true), [fetchChapter]);

    useEffect(() => {
        refresh();
        return () => abortRef.current?.abort();
    }, [refresh]);

    return { data, isLoading, error, refresh, softRefresh };
}

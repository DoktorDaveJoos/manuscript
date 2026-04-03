import { useCallback, useEffect, useRef, useState } from 'react';
import { showJson } from '@/actions/App/Http/Controllers/ChapterController';
import { jsonFetchHeaders } from '@/lib/utils';
import type {
    Chapter,
    Character,
    CharacterChapterPivot,
    ProofreadingConfig,
    ProsePassRule,
    Scene,
} from '@/types/models';

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
};

export default function useChapterData(
    bookId: number,
    chapterId: number | null,
): UseChapterDataReturn {
    const [data, setData] = useState<ChapterData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    const fetchData = useCallback(async () => {
        if (!chapterId) return;

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setData(null);
        setIsLoading(true);
        setError(null);

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
            setData(json);
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                setError((e as Error).message);
            }
        } finally {
            if (!controller.signal.aborted) {
                setIsLoading(false);
            }
        }
    }, [bookId, chapterId]);

    useEffect(() => {
        fetchData();
        return () => abortRef.current?.abort();
    }, [fetchData]);

    return { data, isLoading, error, refresh: fetchData };
}

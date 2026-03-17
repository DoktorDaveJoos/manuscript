import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { analyzeChapter, chapterAnalysisStatus } from '@/actions/App/Http/Controllers/AiController';
import { jsonFetchHeaders } from '@/lib/utils';
import type { Analysis, HookType, InformationDelivery, PacingFeel, ScenePurpose } from '@/types/models';

type AnalysisStatusResponse = {
    analysis_status: 'pending' | 'running' | 'completed' | 'failed' | null;
    analysis_error: string | null;
    analyzed_at: string | null;
    tension_score: number | null;
    hook_score: number | null;
    hook_type: HookType | null;
    summary: string | null;
    scene_purpose: ScenePurpose | null;
    value_shift: string | null;
    emotional_state_open: string | null;
    emotional_state_close: string | null;
    emotional_shift_magnitude: number | null;
    micro_tension_score: number | null;
    pacing_feel: PacingFeel | null;
    entry_hook_score: number | null;
    exit_hook_score: number | null;
    sensory_grounding: number | null;
    information_delivery: InformationDelivery | null;
    analyses: Record<string, Analysis>;
};

const MAX_POLL_COUNT = 150; // ~5 minutes at 2s intervals

export function useChapterAnalysis(
    bookId: number,
    chapterId: number,
    initialStatus: 'pending' | 'running' | 'completed' | 'failed' | null,
    initialAnalyses?: Record<string, Analysis>,
) {
    const [status, setStatus] = useState(initialStatus);
    const [error, setError] = useState<string | null>(null);
    const [analyses, setAnalyses] = useState<Record<string, Analysis>>(initialAnalyses ?? {});
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const pollCountRef = useRef(0);

    const isAnalyzing = status === 'pending' || status === 'running';

    // Sync Inertia props into state — deferred props resolve after mount,
    // and navigating back to an already-analyzed chapter needs fresh data.
    useEffect(() => {
        if (initialAnalyses !== undefined) {
            setAnalyses(initialAnalyses);
        }
    }, [initialAnalyses]);

    useEffect(() => {
        // Don't let stale Inertia props overwrite active polling state
        if (!pollRef.current) {
            setStatus(initialStatus);
        }
    }, [initialStatus]);

    useEffect(() => {
        if (!isAnalyzing) {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
            pollCountRef.current = 0;
            return;
        }

        pollRef.current = setInterval(async () => {
            pollCountRef.current++;

            if (pollCountRef.current > MAX_POLL_COUNT) {
                clearInterval(pollRef.current!);
                pollRef.current = null;
                setStatus('failed');
                setError('Analysis timed out. Please try again.');
                return;
            }

            try {
                const res = await fetch(
                    chapterAnalysisStatus.url({ book: bookId, chapter: chapterId }),
                    { headers: { Accept: 'application/json' } },
                );
                if (!res.ok) throw new Error();
                const data: AnalysisStatusResponse = await res.json();
                setStatus(data.analysis_status);
                setError(data.analysis_error);
                setAnalyses(data.analyses);

                if (data.analysis_status === 'completed' || data.analysis_status === 'failed') {
                    router.reload({ only: ['chapter', 'chapterAnalyses'] });
                }
            } catch {
                // Silently retry on next interval
            }
        }, 2000);

        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [bookId, chapterId, isAnalyzing]);

    const handleAnalyze = useCallback(async () => {
        if (!bookId || !chapterId) {
            setError('Invalid book or chapter. Please reload the page and try again.');
            setStatus('failed');
            return;
        }

        setError(null);
        setStatus('pending');
        pollCountRef.current = 0;

        try {
            const res = await fetch(analyzeChapter.url({ book: bookId, chapter: chapterId }), {
                method: 'POST',
                headers: jsonFetchHeaders(),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Failed to start analysis');
            }
        } catch (e) {
            setError((e as Error).message);
            setStatus('failed');
        }
    }, [bookId, chapterId]);

    return { status, isAnalyzing, error, analyses, handleAnalyze };
}

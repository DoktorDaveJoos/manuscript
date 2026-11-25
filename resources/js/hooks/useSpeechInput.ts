import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { store as transcribe } from '@/actions/App/Http/Controllers/SpeechTranscriptionController';
import { blobToWav16kMono } from '@/lib/audio';

export type SpeechInputState = 'idle' | 'recording' | 'transcribing';

const MAX_RECORDING_MS = 120_000;

type SharedProps = {
    speech_ready: boolean;
};

/**
 * Recording state machine for local speech-to-text. `available` is false
 * until the Whisper model has been downloaded (and the bundled binary
 * exists), so call sites can simply hide the mic. The recorded audio is
 * resampled to WAV in the renderer and transcribed fully on-device.
 */
export function useSpeechInput(onTranscript: (text: string) => void) {
    const { speech_ready } = usePage<SharedProps>().props;
    const { t } = useTranslation('ai');
    const [state, setState] = useState<SpeechInputState>('idle');

    const recorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const discardRef = useRef(false);
    const stopTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Keep the latest callback without re-wiring recorder handlers.
    const onTranscriptRef = useRef(onTranscript);
    onTranscriptRef.current = onTranscript;

    const finishStream = useCallback(() => {
        recorderRef.current?.stream
            .getTracks()
            .forEach((track) => track.stop());
        if (stopTimerRef.current) {
            clearTimeout(stopTimerRef.current);
            stopTimerRef.current = null;
        }
    }, []);

    const transcribeAbortRef = useRef<AbortController | null>(null);
    const startingRef = useRef(false);
    const mountedRef = useRef(true);

    const transcribeRecording = useCallback(
        async (recorded: Blob) => {
            setState('transcribing');
            const controller = new AbortController();
            transcribeAbortRef.current = controller;

            try {
                const wav = await blobToWav16kMono(recorded);
                if (controller.signal.aborted || !mountedRef.current) return;

                const form = new FormData();
                form.append(
                    'audio',
                    new File([wav], 'recording.wav', { type: 'audio/wav' }),
                );

                const response = await fetch(transcribe.url(), {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                    body: form,
                });

                if (!response.ok) {
                    throw new Error(
                        `transcription failed (${response.status})`,
                    );
                }

                const { text } = (await response.json()) as { text: string };
                if (
                    !controller.signal.aborted &&
                    mountedRef.current &&
                    text.trim() !== ''
                ) {
                    onTranscriptRef.current(text.trim());
                }
            } catch (e) {
                if ((e as Error).name === 'AbortError' || !mountedRef.current) {
                    return;
                }
                toast.error(t('speech.failed.title'), {
                    description: t('speech.failed.body'),
                });
            } finally {
                if (transcribeAbortRef.current === controller) {
                    transcribeAbortRef.current = null;
                }
                if (mountedRef.current) {
                    setState('idle');
                }
            }
        },
        [t],
    );

    const start = useCallback(async () => {
        if (startingRef.current || recorderRef.current?.state === 'recording') {
            return;
        }
        startingRef.current = true;
        discardRef.current = false;

        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            startingRef.current = false;
            if (mountedRef.current) {
                toast.error(t('speech.micDenied.title'), {
                    description: t('speech.micDenied.body'),
                });
            }
            return;
        }

        startingRef.current = false;

        if (!mountedRef.current || discardRef.current) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }

        const recorder = new MediaRecorder(stream);
        recorderRef.current = recorder;
        chunksRef.current = [];
        discardRef.current = false;

        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) chunksRef.current.push(event.data);
        };
        recorder.onstop = () => {
            finishStream();
            if (discardRef.current || !mountedRef.current) {
                if (mountedRef.current) setState('idle');
                return;
            }
            void transcribeRecording(
                new Blob(chunksRef.current, { type: recorder.mimeType }),
            );
        };

        recorder.start();
        setState('recording');
        stopTimerRef.current = setTimeout(() => {
            if (recorderRef.current?.state === 'recording') {
                recorderRef.current.stop();
            }
        }, MAX_RECORDING_MS);
    }, [finishStream, t, transcribeRecording]);

    const toggle = useCallback(() => {
        if (state === 'recording') {
            recorderRef.current?.stop();
        } else if (state === 'idle' && !startingRef.current) {
            void start();
        }
    }, [start, state]);

    const cancel = useCallback(() => {
        discardRef.current = true;
        if (state === 'recording') {
            recorderRef.current?.stop();
        } else if (state === 'transcribing') {
            transcribeAbortRef.current?.abort();
            transcribeAbortRef.current = null;
            setState('idle');
        }
    }, [state]);

    // Discard any in-flight recording or transcription when unmounting.
    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
            discardRef.current = true;
            transcribeAbortRef.current?.abort();
            transcribeAbortRef.current = null;
            if (recorderRef.current?.state === 'recording') {
                recorderRef.current.stop();
            }
            finishStream();
        };
    }, [finishStream]);

    return {
        available: speech_ready,
        state,
        toggle,
        cancel,
    };
}

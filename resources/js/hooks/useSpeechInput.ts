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

    const transcribeRecording = useCallback(
        async (recorded: Blob) => {
            setState('transcribing');
            try {
                const wav = await blobToWav16kMono(recorded);
                const form = new FormData();
                form.append(
                    'audio',
                    new File([wav], 'recording.wav', { type: 'audio/wav' }),
                );

                const response = await fetch(transcribe.url(), {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: form,
                });

                if (!response.ok) {
                    throw new Error(
                        `transcription failed (${response.status})`,
                    );
                }

                const { text } = (await response.json()) as { text: string };
                if (text.trim() !== '') {
                    onTranscriptRef.current(text.trim());
                }
            } catch {
                toast.error(t('speech.failed.title'), {
                    description: t('speech.failed.body'),
                });
            } finally {
                setState('idle');
            }
        },
        [t],
    );

    const start = useCallback(async () => {
        let stream: MediaStream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            toast.error(t('speech.micDenied.title'), {
                description: t('speech.micDenied.body'),
            });
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
            if (discardRef.current) {
                setState('idle');
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
        } else if (state === 'idle') {
            void start();
        }
    }, [start, state]);

    const cancel = useCallback(() => {
        if (state === 'recording') {
            discardRef.current = true;
            recorderRef.current?.stop();
        }
    }, [state]);

    // Discard any in-flight recording when the consuming surface unmounts.
    useEffect(
        () => () => {
            discardRef.current = true;
            if (recorderRef.current?.state === 'recording') {
                recorderRef.current.stop();
            }
        },
        [],
    );

    return {
        available: speech_ready,
        state,
        toggle,
        cancel,
    };
}

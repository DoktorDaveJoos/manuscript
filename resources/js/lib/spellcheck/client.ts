import { LOCALE_MAP } from '@/lib/languages';
import type {
    MisspelledRange,
    WorkerRequest,
    WorkerResponse,
} from './protocol';

export interface SpellcheckClient {
    /** Resolves true when the engine is usable, false if it failed to load. */
    whenReady: Promise<boolean>;
    check(text: string): Promise<MisspelledRange[]>;
    suggest(word: string): Promise<string[]>;
    addWord(word: string): void;
    setCustomWords(words: string[]): void;
    /**
     * Subscribe to custom-dictionary changes (addWord / setCustomWords) so
     * every editor sharing this client can recheck, not just the one that
     * triggered the change. Returns an unsubscribe function.
     */
    onWordsChanged(listener: () => void): () => void;
}

const clients = new Map<string, SpellcheckClient>();

/**
 * Shared per-language worker so switching chapters / panes never re-parses
 * the dictionary. Returns null when no dictionary exists for the language
 * or workers are unavailable (SSR).
 */
export function getSpellcheckClient(language: string): SpellcheckClient | null {
    if (typeof Worker === 'undefined') return null;
    const locale = LOCALE_MAP[language];
    if (!locale) return null;

    let client = clients.get(language);
    if (!client) {
        client = createClient(language, locale);
        clients.set(language, client);
    }
    return client;
}

function createClient(language: string, locale: string): SpellcheckClient {
    const worker = new Worker(
        new URL('../../workers/spellcheck.worker.ts', import.meta.url),
        { type: 'module' },
    );

    let nextId = 1;
    const pendingChecks = new Map<number, (r: MisspelledRange[]) => void>();
    const pendingSuggests = new Map<number, (s: string[]) => void>();
    const wordsChangedListeners = new Set<() => void>();

    let markReady: (ok: boolean) => void;
    const whenReady = new Promise<boolean>((resolve) => {
        markReady = resolve;
    });

    const settlePendingWithFailure = (): void => {
        for (const resolve of pendingChecks.values()) resolve([]);
        pendingChecks.clear();
        for (const resolve of pendingSuggests.values()) resolve([]);
        pendingSuggests.clear();
    };

    const notifyWordsChanged = (): void => {
        for (const listener of wordsChangedListeners) listener();
    };

    worker.onmessage = (event: MessageEvent<WorkerResponse>) => {
        const message = event.data;
        if (message.type === 'ready') {
            markReady(true);
        } else if (message.type === 'init-error') {
            console.warn('[Spellcheck] engine unavailable:', message.message);
            markReady(false);
            settlePendingWithFailure();
        } else if (message.type === 'check-result') {
            pendingChecks.get(message.id)?.(message.ranges);
            pendingChecks.delete(message.id);
        } else if (message.type === 'suggest-result') {
            pendingSuggests.get(message.id)?.(message.suggestions);
            pendingSuggests.delete(message.id);
        }
    };
    worker.onerror = (event) => {
        console.warn('[Spellcheck] worker error:', event.message);
        markReady(false);
        settlePendingWithFailure();
    };

    const base = `/dictionaries/${language}/${locale}`;
    const send = (message: WorkerRequest) => worker.postMessage(message);
    send({ type: 'init', affUrl: `${base}.aff`, dicUrl: `${base}.dic` });

    return {
        whenReady,
        check: (text) =>
            new Promise((resolve) => {
                const id = nextId++;
                pendingChecks.set(id, resolve);
                send({ type: 'check', id, text });
            }),
        suggest: (word) =>
            new Promise((resolve) => {
                const id = nextId++;
                pendingSuggests.set(id, resolve);
                send({ type: 'suggest', id, word });
            }),
        addWord: (word) => {
            send({ type: 'add-word', word });
            notifyWordsChanged();
        },
        setCustomWords: (words) => {
            send({ type: 'set-custom-words', words });
            notifyWordsChanged();
        },
        onWordsChanged: (listener) => {
            wordsChangedListeners.add(listener);
            return () => wordsChangedListeners.delete(listener);
        },
    };
}

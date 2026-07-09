import type { Hunspell } from 'hunspell-asm';
import { loadHunspell } from '@/lib/spellcheck/loadHunspell';
import type {
    MisspelledRange,
    WorkerRequest,
    WorkerResponse,
} from '@/lib/spellcheck/protocol';
import { tokenizeWords } from '@/lib/spellcheck/tokenize';

let hunspell: Hunspell | null = null;
let customWords = new Set<string>();
// Prose repeats words heavily — memoizing spell() makes rechecks near-free.
const cache = new Map<string, boolean>();

function post(message: WorkerResponse): void {
    self.postMessage(message);
}

async function fetchBuffer(url: string): Promise<Uint8Array> {
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`dictionary fetch failed: ${response.status} ${url}`);
    }
    return new Uint8Array(await response.arrayBuffer());
}

async function init(affUrl: string, dicUrl: string): Promise<void> {
    const [factory, aff, dic] = await Promise.all([
        loadHunspell(),
        fetchBuffer(affUrl),
        fetchBuffer(dicUrl),
    ]);
    const affPath = factory.mountBuffer(aff, 'dict.aff');
    const dicPath = factory.mountBuffer(dic, 'dict.dic');
    hunspell = factory.create(affPath, dicPath);
}

function isCorrect(word: string): boolean {
    if (customWords.has(word.toLowerCase())) return true;
    let known = cache.get(word);
    if (known === undefined) {
        known = hunspell!.spell(word);
        cache.set(word, known);
    }
    return known;
}

self.onmessage = (event: MessageEvent<WorkerRequest>) => {
    const message = event.data;
    switch (message.type) {
        case 'init':
            init(message.affUrl, message.dicUrl)
                .then(() => post({ type: 'ready' }))
                .catch((error: unknown) =>
                    post({ type: 'init-error', message: String(error) }),
                );
            break;
        case 'check': {
            if (!hunspell) return;
            const ranges: MisspelledRange[] = [];
            for (const token of tokenizeWords(message.text)) {
                if (!isCorrect(token.word)) {
                    ranges.push({
                        from: token.from,
                        to: token.to,
                        word: token.word,
                    });
                }
            }
            post({ type: 'check-result', id: message.id, ranges });
            break;
        }
        case 'suggest':
            post({
                type: 'suggest-result',
                id: message.id,
                suggestions: hunspell
                    ? hunspell.suggest(message.word).slice(0, 5)
                    : [],
            });
            break;
        case 'add-word':
            customWords.add(message.word.toLowerCase());
            break;
        case 'set-custom-words':
            customWords = new Set(
                message.words.map((word) => word.toLowerCase()),
            );
            break;
    }
};

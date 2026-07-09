export interface MisspelledRange {
    from: number; // offset within the checked text
    to: number;
    word: string;
}

export type WorkerRequest =
    | { type: 'init'; affUrl: string; dicUrl: string }
    | { type: 'check'; id: number; text: string }
    | { type: 'suggest'; id: number; word: string }
    | { type: 'add-word'; word: string }
    | { type: 'set-custom-words'; words: string[] };

export type WorkerResponse =
    | { type: 'ready' }
    | { type: 'init-error'; message: string }
    | { type: 'check-result'; id: number; ranges: MisspelledRange[] }
    | { type: 'suggest-result'; id: number; suggestions: string[] };

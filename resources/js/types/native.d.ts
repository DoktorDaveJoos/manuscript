interface NativeBridge {
    on(event: string, callback: (payload: any, event: string) => void): void;
    contextMenu(template: unknown[]): void;
}

interface SpellcheckBridge {
    isWordMisspelled(word: string): boolean;
    getWordSuggestions(word: string): string[];
    setLanguages(languages: string[]): void;
    addToDictionary(word: string): void;
}

interface Window {
    Native?: NativeBridge;
    Spellcheck?: SpellcheckBridge;
}

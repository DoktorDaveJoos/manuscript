interface NativeBridge {
    on(event: string, callback: (payload: any, event: string) => void): void;
    contextMenu(template: unknown[]): void;
}

interface Window {
    Native?: NativeBridge;
}

declare module 'hunspell-asm/dist/cjs/hunspellLoader.js' {
    import type { HunspellFactory } from 'hunspell-asm';
    export const hunspellLoader: (asmModule: unknown) => HunspellFactory;
}

declare module 'hunspell-asm/dist/cjs/lib/browser/hunspell.js' {
    interface HunspellRuntimeModule {
        initializeRuntime(timeout?: number): Promise<boolean>;
        [key: string]: unknown;
    }
    const runtimeFactory: (
        moduleArg: Record<string, unknown>,
    ) => HunspellRuntimeModule;
    export default runtimeFactory;
}

declare module 'emscripten-wasm-loader/dist/esm/constructModule.js' {
    export const constructModule: (
        value: Record<string, unknown>,
        binaryRemoteEndpoint?: string,
    ) => {
        initializeRuntime(timeout?: number): Promise<boolean>;
        [key: string]: unknown;
    };
}

import { constructModule } from 'emscripten-wasm-loader/dist/esm/constructModule.js';
import type { HunspellFactory } from 'hunspell-asm';
// hunspell-asm's own loadModule() is broken under bundlers: its ESM build
// namespace-imports CJS emscripten UMD files (and the Node runtime rather
// than the browser one), which Rollup turns into non-callable namespace
// objects. The CJS build uses plain require() chains that Vite's commonjs
// interop converts correctly, so we replicate the small loadModule flow on
// top of the CJS internals with the browser runtime.
import { hunspellLoader } from 'hunspell-asm/dist/cjs/hunspellLoader.js';
import runtimeFactory from 'hunspell-asm/dist/cjs/lib/browser/hunspell.js';

const INIT_TIMEOUT_MS = 30000;

export async function loadHunspell(): Promise<HunspellFactory> {
    const constructed = constructModule({});
    const asmModule = runtimeFactory(constructed);
    const initialized = await asmModule.initializeRuntime(INIT_TIMEOUT_MS);
    if (!initialized) {
        throw new Error('hunspell wasm runtime failed to initialize in time');
    }
    return hunspellLoader(asmModule);
}

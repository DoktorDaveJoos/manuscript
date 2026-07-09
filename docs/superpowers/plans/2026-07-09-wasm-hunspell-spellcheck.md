# WASM Hunspell Spell Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace Chromium's passive, unreliable spellcheck with a deterministic pipeline — real Hunspell (WASM) in a Web Worker rendering ProseMirror decorations — so squiggles appear on chapter load, survive re-renders, and work identically in dev, packaged app, and browser tests.

**Architecture:** A TipTap/ProseMirror extension queues text blocks for checking (whole doc on load, changed blocks on edit, 300 ms debounce) and renders misspelled ranges as inline decorations. A per-language singleton Web Worker hosts `hunspell-asm` + the book's dictionary fetched from `public/dictionaries/{lang}/{locale}.aff|.dic`. Right-click on a squiggle asks the worker for suggestions; "Add to Dictionary" updates both the worker accept-list and the existing backend per-book dictionary.

**Tech Stack:** hunspell-asm 4.0.2 (already a dependency), wooorm `dictionary-*` npm packages (dictionary data, devDependencies), TipTap v2 / prosemirror-view Decorations, Vite module workers, Pest v4 browser tests.

**Spec:** `docs/superpowers/specs/2026-07-09-wasm-hunspell-spellcheck-design.md`

## Global Constraints

- Working branch is `dev`. NEVER `git checkout` / `git switch`. All commits on `dev`.
- After modifying PHP files: `vendor/bin/pint --dirty --format agent`.
- Browser tests require a production build first: `npm run build`. If `public/hot` exists (dev server running), move it aside (`mv public/hot public/hot.bak`) before running browser tests and restore after — never delete it.
- Run tests with `php artisan test --compact --filter=<name>`.
- No auth checks anywhere (repo guardrail). No new controllers (so no new Feature-test obligation). No migrations in this plan.
- Repo red-green rule: the browser test for the squiggle bug is committed in the same commit as the engine (single coherent unit) — include `// red-green: see SpellcheckTest` in that commit message body.
- Do NOT touch `scripts/nativephp-patches/files/**` or `nativephp/electron/**` (fragile patched Electron surface). The preload `Spellcheck` bridge stays as harmless dead code; only app-side references are removed (Task 5).
- Never edit the main repo `.env`.
- Commit messages end with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- New devDependencies in this plan (`dictionary-de|es|fr|it|nl|pt|sv`) are pre-approved via the spec. No other dependency changes.
- Frontend code must respect the design system: no hardcoded hex in `.tsx`, use `--color-delete` token for the squiggle color in CSS.

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `scripts/copy-dictionaries.mjs` | Copy `.aff`/`.dic` from `dictionary-*` packages into `public/dictionaries/` |
| Modify | `package.json` | Point `dictionaries:copy` at the script; add `dictionary-*` devDeps |
| Modify | `resources/js/lib/languages.ts` | Add Swedish |
| Delete | `public/dictionaries/de/de_DE.words` | 32 MB typo-js-era artifact, unreferenced |
| Create | `resources/js/lib/spellcheck/tokenize.ts` | Pure tokenizer: text → word tokens with offsets |
| Create | `resources/js/lib/spellcheck/protocol.ts` | Worker message types |
| Create | `resources/js/workers/spellcheck.worker.ts` | Hunspell WASM host, word cache, custom-word accept-list |
| Create | `resources/js/lib/spellcheck/client.ts` | Promise-based worker client, per-language singleton |
| Create | `resources/js/extensions/SpellcheckExtension.ts` | ProseMirror plugin: dirty tracking, decorations, context menu |
| Modify | `resources/css/app.css` | `.spell-error` squiggle style |
| Modify | `resources/js/hooks/useChapterEditor.ts` | Swap SpellcheckContextMenu → SpellcheckExtension; kill DOM-attr spellcheck |
| Modify | `resources/js/components/editor/SceneEditor.tsx` | Thread `customWords` / `onAddToDictionary` |
| Modify | `resources/js/components/editor/WritingSurface.tsx` | Thread `customWords` / `onAddToDictionary` |
| Modify | `resources/js/components/editor/ChapterPane.tsx` | Feed `chapterData.customDictionary` + `addToDictionary` into the chain |
| Create | `tests/Browser/SpellcheckTest.php` | Feature browser tests (squiggles on load, popover, dictionary, German, toggle) |
| Delete | `resources/js/extensions/SpellcheckContextMenu.ts` | Replaced (Task 5) |
| Modify | `resources/js/types/native.d.ts` | Remove `SpellcheckBridge` (Task 5) |
| Modify | `app/Providers/NativeAppServiceProvider.php` | Drop `webPreferences(['spellcheck' => true])` (Task 5) |

---

### Task 1: Dictionary pipeline + Swedish

**Files:**
- Create: `scripts/copy-dictionaries.mjs`
- Modify: `package.json` (`dictionaries:copy` script, devDependencies)
- Modify: `resources/js/lib/languages.ts`
- Delete: `public/dictionaries/de/de_DE.words`

**Interfaces:**
- Produces: `public/dictionaries/{lang}/{locale}.aff` + `.dic` for de, en, es, fr, it, nl, pt, sv — fetched by the worker in Task 2 at URL `/dictionaries/{lang}/{locale}.aff|.dic`.
- Produces: `BOOK_LANGUAGES` entry `{ value: 'sv', label: 'Svenska', locale: 'sv_SE' }`; `LOCALE_MAP['sv'] === 'sv_SE'`.

- [ ] **Step 1: Install dictionary packages**

`dictionary-en@^4.0.0` is already a devDependency. Add the rest:

```bash
cd /Users/david/Workspace/manuscript && npm install --save-dev dictionary-de dictionary-es dictionary-fr dictionary-it dictionary-nl dictionary-pt dictionary-sv
```

Expected: npm succeeds; `package.json` devDependencies gain the 7 packages.

- [ ] **Step 2: Write the copy script**

Create `scripts/copy-dictionaries.mjs`:

```js
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const LANGUAGES = [
    ['de', 'de_DE'],
    ['en', 'en_US'],
    ['es', 'es_ES'],
    ['fr', 'fr_FR'],
    ['it', 'it_IT'],
    ['nl', 'nl_NL'],
    ['pt', 'pt_PT'],
    ['sv', 'sv_SE'],
];

for (const [lang, locale] of LANGUAGES) {
    const pkg = join(root, 'node_modules', `dictionary-${lang}`);
    const out = join(root, 'public', 'dictionaries', lang);
    mkdirSync(out, { recursive: true });
    copyFileSync(join(pkg, 'index.aff'), join(out, `${locale}.aff`));
    copyFileSync(join(pkg, 'index.dic'), join(out, `${locale}.dic`));
    console.log(`dictionaries: ${lang} -> ${locale}`);
}
```

- [ ] **Step 3: Point the npm script at it**

In `package.json`, replace the inline `dictionaries:copy` one-liner:

```json
"dictionaries:copy": "node scripts/copy-dictionaries.mjs",
```

(The `build` script already runs `npm run dictionaries:copy && vite build` — leave it.)

- [ ] **Step 4: Run it and delete the dead words file**

```bash
cd /Users/david/Workspace/manuscript && npm run dictionaries:copy && rm public/dictionaries/de/de_DE.words
```

Expected: 8 `dictionaries: xx -> xx_XX` lines. Verify:

```bash
ls public/dictionaries/sv/ && ls public/dictionaries/de/
```

Expected: `sv_SE.aff sv_SE.dic`; de contains only `de_DE.aff de_DE.dic` (no `.words`). Note: this refreshes all committed dictionaries to pristine wooorm/LibreOffice versions (the old committed German pair predates this pipeline) — the diff on `public/dictionaries/` is expected and should be committed.

- [ ] **Step 5: Add Swedish to `resources/js/lib/languages.ts`**

In the `BOOK_LANGUAGES` array, after the `pt` entry, add:

```ts
    { value: 'sv', label: 'Svenska', locale: 'sv_SE' },
```

(Backend needs no change — `StoreBookRequest` validates `language` as `string|max:5`, not an enum.)

- [ ] **Step 6: Sanity-run existing tests**

```bash
cd /Users/david/Workspace/manuscript && php artisan test --compact --filter=BookSettings
```

Expected: PASS (language list is not enum-validated anywhere).

- [ ] **Step 7: Commit**

```bash
cd /Users/david/Workspace/manuscript && git add scripts/copy-dictionaries.mjs package.json package-lock.json resources/js/lib/languages.ts public/dictionaries && git commit -m "feat(spellcheck): dictionary pipeline for 8 languages incl. Swedish

Copy pristine wooorm/LibreOffice Hunspell dictionaries from dictionary-*
packages at build time; drop the 32MB typo-js-era de_DE.words artifact.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

IMPORTANT: `git status` first — the working tree carries long-lived unrelated WIP; stage ONLY the files listed above, never `git add -A`.

---

### Task 2: Engine + decorations (the squiggle fix), red → green

**Files:**
- Create: `resources/js/lib/spellcheck/tokenize.ts`
- Create: `resources/js/lib/spellcheck/protocol.ts`
- Create: `resources/js/workers/spellcheck.worker.ts`
- Create: `resources/js/lib/spellcheck/client.ts`
- Create: `resources/js/extensions/SpellcheckExtension.ts`
- Modify: `resources/css/app.css`
- Modify: `resources/js/hooks/useChapterEditor.ts`
- Test: `tests/Browser/SpellcheckTest.php`

**Interfaces:**
- Consumes: `LOCALE_MAP` from `@/lib/languages` (Task 1); dictionaries at `/dictionaries/{lang}/{locale}.aff|.dic` (Task 1).
- Produces: `getSpellcheckClient(language: string): SpellcheckClient | null` with `whenReady: Promise<boolean>`, `check(text): Promise<MisspelledRange[]>`, `suggest(word): Promise<string[]>`, `addWord(word): void`, `setCustomWords(words: string[]): void`.
- Produces: `SpellcheckExtension` (TipTap extension) with options `{ language: string; enabledRef?: RefObject<boolean>; customWords: string[]; onAddToDictionary?: (word: string) => void }` and exported `spellcheckPluginKey` accepting metas `{ type: 'set-enabled', enabled }` and `{ type: 'recheck-all' }`.
- Produces: `.spell-error` decoration class on misspelled words.

**Note on TDD:** the browser test is written first and MUST fail before the implementation lands. Test and implementation are committed together (single coherent unit) with the red-green marker in the commit message. Activate the `pest-testing` skill before writing the test file.

- [ ] **Step 1: Write the failing browser test**

Create `tests/Browser/SpellcheckTest.php`:

```php
<?php

use App\Models\Book;

it('shows spell-error decorations on chapter load without touching the text', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $content = '<p>This sentens contains one mispeled word or two.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error');
});

it('does not flag correctly spelled English text', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $content = '<p>This sentence contains only correctly spelled words.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertMissing('.editor-prose .spell-error');
});
```

If `createBookWithChapters` sets a `currentVersion` relation name differently, check `tests/Pest.php:99` — the helper creates `ChapterVersion::factory()->for($chapter)->create(['is_current' => true, ...])`; the accessor on the model is `currentVersion`. Verify with `grep -n "currentVersion" app/Models/Chapter.php` and adapt if needed.

- [ ] **Step 2: Run it — must be RED**

```bash
cd /Users/david/Workspace/manuscript && npm run build && php artisan test --compact --filter=SpellcheckTest
```

Expected: FAIL — `.editor-prose .spell-error` not present (first test). Second test passes trivially (no decorations exist at all yet) — that's fine; it becomes meaningful once the engine lands.

- [ ] **Step 3: Tokenizer**

Create `resources/js/lib/spellcheck/tokenize.ts`:

```ts
export interface WordToken {
    word: string;
    from: number; // offset within the text
    to: number;
}

const WORD_RE = /[\p{L}\p{M}'’]+/gu;

/**
 * Split text into checkable word tokens with offsets.
 * Skips: tokens attached to digits ("2nd" -> "nd"), ALL-CAPS words
 * (acronyms / shouting), and bare apostrophes.
 */
export function tokenizeWords(text: string): WordToken[] {
    const tokens: WordToken[] = [];
    for (const match of text.matchAll(WORD_RE)) {
        let word = match[0];
        let from = match.index;
        while (word.length > 0 && /^['’]/.test(word)) {
            word = word.slice(1);
            from++;
        }
        while (word.length > 0 && /['’]$/.test(word)) {
            word = word.slice(0, -1);
        }
        if (word.length === 0) continue;

        const before = text[from - 1];
        const after = text[from + word.length];
        if ((before && /\d/.test(before)) || (after && /\d/.test(after))) {
            continue;
        }
        if (word.length > 1 && word === word.toUpperCase()) continue;

        tokens.push({ word, from, to: from + word.length });
    }
    return tokens;
}
```

- [ ] **Step 4: Worker protocol**

Create `resources/js/lib/spellcheck/protocol.ts`:

```ts
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
```

- [ ] **Step 5: The worker**

Create `resources/js/workers/spellcheck.worker.ts`:

```ts
import type { Hunspell } from 'hunspell-asm';
import { loadModule } from 'hunspell-asm';
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
        loadModule(),
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
```

- [ ] **Step 6: The client (per-language singleton)**

Create `resources/js/lib/spellcheck/client.ts`:

```ts
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

    let markReady: (ok: boolean) => void;
    const whenReady = new Promise<boolean>((resolve) => {
        markReady = resolve;
    });

    worker.onmessage = (event: MessageEvent<WorkerResponse>) => {
        const message = event.data;
        if (message.type === 'ready') {
            markReady(true);
        } else if (message.type === 'init-error') {
            console.warn('[Spellcheck] engine unavailable:', message.message);
            markReady(false);
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
        addWord: (word) => send({ type: 'add-word', word }),
        setCustomWords: (words) => send({ type: 'set-custom-words', words }),
    };
}
```

- [ ] **Step 7: The extension**

Create `resources/js/extensions/SpellcheckExtension.ts`. This is the core; read the inline comments — they encode the async-correctness invariants:

```ts
import { Extension } from '@tiptap/core';
import type { Node as PMNode } from '@tiptap/pm/model';
import type { EditorState, Transaction } from '@tiptap/pm/state';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Mapping } from '@tiptap/pm/transform';
import type { EditorView } from '@tiptap/pm/view';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import type { RefObject } from 'react';
import { createSpellcheckPopover } from '@/components/editor/SpellcheckPopover';
import type { SpellcheckClient } from '@/lib/spellcheck/client';
import { getSpellcheckClient } from '@/lib/spellcheck/client';
import type { MisspelledRange } from '@/lib/spellcheck/protocol';

const DEBOUNCE_MS = 300;
// Placeholder for non-text leaf nodes (e.g. hardBreak) so text offsets
// stay aligned with document positions. Never matches the word regex.
const LEAF_CHAR = '￼';

interface SpellcheckPluginState {
    decorations: DecorationSet;
    enabled: boolean;
    /** Doc range needing a recheck; -1 = clean. Mapped through edits. */
    dirtyFrom: number;
    dirtyTo: number;
    /**
     * Cumulative step mapping since the last idle reset. Async check
     * results carry the mapping length at request time; slicing from it
     * maps request-time positions to current positions.
     */
    mapping: Mapping;
}

type SpellcheckMeta =
    | {
          type: 'results';
          blockPos: number;
          mappingLength: number;
          text: string;
          ranges: MisspelledRange[];
      }
    | { type: 'set-enabled'; enabled: boolean }
    | { type: 'recheck-all' }
    | { type: 'clear-dirty' }
    | { type: 'reset-mapping' };

export const spellcheckPluginKey = new PluginKey<SpellcheckPluginState>(
    'spellcheck',
);

function blockText(node: PMNode): string {
    return node.textBetween(0, node.content.size, undefined, LEAF_CHAR);
}

function markDirty(
    state: SpellcheckPluginState,
    from: number,
    to: number,
): void {
    state.dirtyFrom = state.dirtyFrom < 0 ? from : Math.min(state.dirtyFrom, from);
    state.dirtyTo = Math.max(state.dirtyTo, to);
}

function applyTransaction(
    tr: Transaction,
    prev: SpellcheckPluginState,
): SpellcheckPluginState {
    const next: SpellcheckPluginState = { ...prev };

    if (tr.docChanged) {
        next.decorations = next.decorations.map(tr.mapping, tr.doc);
        next.mapping = new Mapping([...next.mapping.maps, ...tr.mapping.maps]);
        if (next.dirtyFrom >= 0) {
            next.dirtyFrom = tr.mapping.map(next.dirtyFrom, -1);
            next.dirtyTo = tr.mapping.map(next.dirtyTo, 1);
        }
        // Union in this transaction's changed ranges (in final coordinates).
        tr.mapping.maps.forEach((stepMap, index) => {
            const rest = tr.mapping.slice(index + 1);
            stepMap.forEach((_oldStart, _oldEnd, newStart, newEnd) => {
                markDirty(next, rest.map(newStart, -1), rest.map(newEnd, 1));
            });
        });
    }

    const meta = tr.getMeta(spellcheckPluginKey) as SpellcheckMeta | undefined;
    if (!meta) return next;

    switch (meta.type) {
        case 'set-enabled':
            next.enabled = meta.enabled;
            if (meta.enabled) {
                markDirty(next, 0, tr.doc.content.size);
            } else {
                next.decorations = DecorationSet.empty;
                next.dirtyFrom = -1;
                next.dirtyTo = -1;
            }
            break;
        case 'recheck-all':
            if (next.enabled) markDirty(next, 0, tr.doc.content.size);
            break;
        case 'clear-dirty':
            next.dirtyFrom = -1;
            next.dirtyTo = -1;
            break;
        case 'reset-mapping':
            next.mapping = new Mapping();
            break;
        case 'results': {
            if (!next.enabled) break;
            // Map the request-time block position to the present, then
            // verify the block still holds the exact text that was checked.
            // If it changed, that edit already re-marked the block dirty —
            // dropping the stale result is safe.
            const mapped = next.mapping
                .slice(meta.mappingLength)
                .map(meta.blockPos, -1);
            const node = tr.doc.nodeAt(mapped);
            if (!node || !node.isTextblock || blockText(node) !== meta.text) {
                break;
            }
            const contentStart = mapped + 1;
            const contentEnd = contentStart + node.content.size;
            next.decorations = next.decorations.remove(
                next.decorations.find(contentStart, contentEnd),
            );
            next.decorations = next.decorations.add(
                tr.doc,
                meta.ranges.map((range) =>
                    Decoration.inline(
                        contentStart + range.from,
                        contentStart + range.to,
                        { class: 'spell-error' },
                    ),
                ),
            );
            break;
        }
    }
    return next;
}

class SpellcheckView {
    private timer: number | null = null;
    private destroyed = false;
    private engineOk = false;
    private inflight = 0;

    constructor(
        private readonly view: EditorView,
        private readonly client: SpellcheckClient,
        customWords: string[],
    ) {
        void this.boot(customWords);
    }

    private async boot(customWords: string[]): Promise<void> {
        this.engineOk = await this.client.whenReady;
        if (!this.engineOk || this.destroyed) return;
        this.client.setCustomWords(customWords);
        this.schedule();
    }

    update(): void {
        const state = spellcheckPluginKey.getState(this.view.state);
        if (state && state.enabled && state.dirtyFrom >= 0) this.schedule();
    }

    private schedule(): void {
        if (!this.engineOk) return;
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => {
            this.timer = null;
            this.flush();
        }, DEBOUNCE_MS);
    }

    private dispatchMeta(meta: SpellcheckMeta): void {
        const tr = this.view.state.tr
            .setMeta(spellcheckPluginKey, meta)
            .setMeta('addToHistory', false);
        this.view.dispatch(tr);
    }

    private flush(): void {
        if (this.destroyed || !this.engineOk) return;
        const state = spellcheckPluginKey.getState(this.view.state);
        if (!state || !state.enabled || state.dirtyFrom < 0) return;

        const doc = this.view.state.doc;
        const from = Math.max(0, Math.min(state.dirtyFrom, doc.content.size));
        const to = Math.max(from, Math.min(state.dirtyTo, doc.content.size));
        const mappingLength = state.mapping.maps.length;

        // Snapshot the blocks BEFORE clearing dirty, then clear synchronously
        // so edits arriving while checks are in flight re-dirty cleanly.
        const blocks: Array<{ pos: number; text: string }> = [];
        doc.nodesBetween(from, to, (node, pos) => {
            if (node.isTextblock) {
                blocks.push({ pos, text: blockText(node) });
                return false;
            }
            return true;
        });
        this.dispatchMeta({ type: 'clear-dirty' });

        for (const block of blocks) {
            this.inflight++;
            void this.client.check(block.text).then((ranges) => {
                this.inflight--;
                if (this.destroyed) return;
                this.dispatchMeta({
                    type: 'results',
                    blockPos: block.pos,
                    mappingLength,
                    text: block.text,
                    ranges,
                });
                // With no checks in flight the accumulated mapping can be
                // dropped — keeps memory flat over long sessions.
                const current = spellcheckPluginKey.getState(this.view.state);
                if (
                    this.inflight === 0 &&
                    current &&
                    current.dirtyFrom < 0 &&
                    current.mapping.maps.length > 0
                ) {
                    this.dispatchMeta({ type: 'reset-mapping' });
                }
            });
        }
    }

    destroy(): void {
        this.destroyed = true;
        if (this.timer !== null) window.clearTimeout(this.timer);
        // The worker is a shared per-language singleton — never terminated here.
    }
}

export interface SpellcheckOptions {
    language: string;
    enabledRef?: RefObject<boolean>;
    customWords: string[];
    onAddToDictionary?: (word: string) => void;
}

export const SpellcheckExtension = Extension.create<SpellcheckOptions>({
    name: 'spellcheck',

    addOptions() {
        return {
            language: 'en',
            enabledRef: undefined,
            customWords: [],
            onAddToDictionary: undefined,
        };
    },

    addProseMirrorPlugins() {
        const { language, enabledRef, customWords, onAddToDictionary } =
            this.options;

        const client = getSpellcheckClient(language);
        if (!client) return [];

        let activePopover: { destroy: () => void } | null = null;

        return [
            new Plugin<SpellcheckPluginState>({
                key: spellcheckPluginKey,
                state: {
                    init: (_config, state: EditorState) => ({
                        decorations: DecorationSet.empty,
                        enabled: enabledRef?.current ?? true,
                        dirtyFrom: 0,
                        dirtyTo: state.doc.content.size,
                        mapping: new Mapping(),
                    }),
                    apply: applyTransaction,
                },
                props: {
                    decorations(state) {
                        return spellcheckPluginKey.getState(state)
                            ?.decorations;
                    },
                    handleDOMEvents: {
                        contextmenu(view, event) {
                            activePopover?.destroy();
                            activePopover = null;

                            const pluginState = spellcheckPluginKey.getState(
                                view.state,
                            );
                            if (!pluginState?.enabled) return false;

                            const pos = view.posAtCoords({
                                left: event.clientX,
                                top: event.clientY,
                            });
                            if (!pos) return false;

                            const deco = pluginState.decorations.find(
                                pos.pos,
                                pos.pos,
                            )[0];
                            if (!deco) return false;

                            const word = view.state.doc.textBetween(
                                deco.from,
                                deco.to,
                            );
                            event.preventDefault();

                            void client.suggest(word).then((suggestions) => {
                                activePopover = createSpellcheckPopover({
                                    suggestions,
                                    position: {
                                        x: event.clientX,
                                        y: event.clientY,
                                    },
                                    onReplace: (replacement) => {
                                        const { doc, tr } = view.state;
                                        if (
                                            doc.textBetween(
                                                deco.from,
                                                deco.to,
                                            ) !== word
                                        ) {
                                            return;
                                        }
                                        tr.insertText(
                                            replacement,
                                            deco.from,
                                            deco.to,
                                        );
                                        view.dispatch(tr);
                                    },
                                    onAddToDictionary: () => {
                                        client.addWord(word);
                                        onAddToDictionary?.(word);
                                        view.dispatch(
                                            view.state.tr
                                                .setMeta(spellcheckPluginKey, {
                                                    type: 'recheck-all',
                                                })
                                                .setMeta(
                                                    'addToHistory',
                                                    false,
                                                ),
                                        );
                                    },
                                });
                            });
                            return true;
                        },
                    },
                },
                view: (editorView) => {
                    const spellcheckView = new SpellcheckView(
                        editorView,
                        client,
                        customWords,
                    );
                    return {
                        update: () => spellcheckView.update(),
                        destroy: () => {
                            spellcheckView.destroy();
                            activePopover?.destroy();
                            activePopover = null;
                        },
                    };
                },
            }),
        ];
    },
});
```

- [ ] **Step 8: Squiggle CSS**

In `resources/css/app.css`, next to the other `.editor-prose` rules (search for `.editor-prose`), add:

```css
.editor-prose .spell-error {
    text-decoration: underline dotted var(--color-delete);
    text-decoration-skip-ink: none;
    text-underline-offset: 3px;
}
```

- [ ] **Step 9: Wire into `useChapterEditor.ts`**

In `resources/js/hooks/useChapterEditor.ts`:

1. Replace the import of `SpellcheckContextMenu` with:

```ts
import {
    SpellcheckExtension,
    spellcheckPluginKey,
} from '@/extensions/SpellcheckExtension';
```

2. Add two params to the hook signature (after `spellcheckEnabled = true`): `customWords = []` and `onAddToDictionary` with types `customWords?: string[]; onAddToDictionary?: (word: string) => void;`.

3. Replace the `SpellcheckContextMenu.configure({ enabledRef: spellcheckEnabledRef })` entry in the extensions array with:

```ts
                SpellcheckExtension.configure({
                    language: language ?? 'en',
                    enabledRef: spellcheckEnabledRef,
                    customWords,
                    onAddToDictionary,
                }),
```

4. In `editorProps.attributes`, add `spellcheck: 'false'` next to `class: 'editor-prose'` — this permanently kills Chromium's own flaky squiggles so they never double up with ours.

5. Replace the entire "Toggle the spellcheck DOM attribute" `useEffect` (the one calling `editor.view.dom.setAttribute('spellcheck', ...)`) with:

```ts
    // Toggle spellcheck decorations without recreating the editor.
    useEffect(() => {
        if (!editor || editor.isDestroyed) return;
        editor.view.dispatch(
            editor.state.tr
                .setMeta(spellcheckPluginKey, {
                    type: 'set-enabled',
                    enabled: spellcheckEnabled,
                })
                .setMeta('addToHistory', false),
        );
    }, [editor, spellcheckEnabled]);
```

Note: `customWords` must NOT join `proofreadingKey` — adding a word must never recreate the editor. The array is read once at editor creation; live additions flow through `client.addWord`.

- [ ] **Step 10: Build + run — must be GREEN**

```bash
cd /Users/david/Workspace/manuscript && npm run build && php artisan test --compact --filter=SpellcheckTest
```

Expected: both tests PASS. If the first test fails with the worker never becoming ready, debug order: (1) browser console errors in the test output (`assertNoJavaScriptErrors` will surface them), (2) `hunspell-asm` bundling under Vite — if its internal node/browser switch breaks the worker bundle, add to `vite.config.ts`: `resolve: { alias: { 'hunspell-asm': 'hunspell-asm/dist/esm/index.js' } }` — and if that still fails, STOP and report (the spec names Nuspell/WASM as the fallback engine; that's a design-level swap needing user sign-off).

- [ ] **Step 11: Make sure the existing editor suite still passes**

```bash
cd /Users/david/Workspace/manuscript && php artisan test --compact --filter=ChapterEditorTest
```

Expected: PASS (the old context-menu extension is still in the tree but no longer wired; nothing else changed behaviorally).

- [ ] **Step 12: Lint, typecheck, commit**

```bash
cd /Users/david/Workspace/manuscript && npm run lint && npm run types:check && npm run format
```

Expected: clean. Then:

```bash
cd /Users/david/Workspace/manuscript && git add tests/Browser/SpellcheckTest.php resources/js/lib/spellcheck resources/js/workers/spellcheck.worker.ts resources/js/extensions/SpellcheckExtension.ts resources/css/app.css resources/js/hooks/useChapterEditor.ts && git commit -m "feat(spellcheck): deterministic squiggles via WASM Hunspell worker

Chromium's spellchecker only marks text as it's typed or focused, so
loaded chapters showed no squiggles. Own the pipeline instead: Hunspell
(hunspell-asm) in a shared per-language Web Worker checks blocks and the
editor renders ProseMirror decorations — present on load, stable across
re-renders, obeying the book language.

// red-green: see SpellcheckTest

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Stage ONLY the listed files (`git status` first; the tree carries unrelated WIP).

---

### Task 3: Suggestions popover + custom dictionary plumbing

**Files:**
- Modify: `resources/js/components/editor/ChapterPane.tsx`
- Modify: `resources/js/components/editor/WritingSurface.tsx`
- Modify: `resources/js/components/editor/SceneEditor.tsx`
- Test: `tests/Browser/SpellcheckTest.php` (extend)

**Interfaces:**
- Consumes: `useProofreading(initialConfig, initialDictionary, bookId)` → `{ dictionary, addToDictionary }` (exists, `resources/js/hooks/useProofreading.ts`); `chapterData.customDictionary?: string[]` (exists, `resources/js/hooks/useChapterData.ts:23`); `SpellcheckExtension` options from Task 2.
- Produces: right-click popover on squiggles with replace / add-to-dictionary, persisting to `PUT /books/{book}/settings/custom-dictionary`.

- [ ] **Step 1: Write the failing browser tests**

Append to `tests/Browser/SpellcheckTest.php`:

```php
it('replaces a misspelled word from the right-click popover', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $content = '<p>The knight was mispeled here.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->rightClick('.editor-prose .spell-error')
        ->wait(1)
        ->assertSee('Add to Dictionary');
});

it('adds a word to the custom dictionary and clears its squiggle', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $content = '<p>The wizard Zaphrandor smiled.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->rightClick('.editor-prose .spell-error')
        ->wait(1)
        ->click('Add to Dictionary')
        ->wait(2)
        ->assertMissing('.editor-prose .spell-error');

    expect($book->fresh()->custom_dictionary)->toContain('zaphrandor');
});

it('never flags words already in the custom dictionary', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['custom_dictionary' => ['zaphrandor']]);
    $chapter = $chapters[0];
    $content = '<p>The wizard Zaphrandor smiled.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertMissing('.editor-prose .spell-error');
});
```

If `rightClick()` is not available in this Pest version, consult the `pest-testing` skill for the current context-click API before improvising.

- [ ] **Step 2: Run — the popover tests must be RED**

```bash
cd /Users/david/Workspace/manuscript && npm run build && php artisan test --compact --filter=SpellcheckTest
```

Expected: the two popover tests FAIL (popover appears but "Add to Dictionary" doesn't persist / custom words not honored — `customWords` is still hardwired empty). The pre-seeded dictionary test FAILS because ChapterPane passes `[]`.

- [ ] **Step 3: Thread the props**

`resources/js/components/editor/ChapterPane.tsx`:

1. Change the `useProofreading` call to feed the server-provided dictionary and capture the mutators:

```ts
    const {
        config: proofreadingConfig,
        dictionary: customDictionary,
        addToDictionary,
    } = useProofreading(
        initialProofreadingConfig ?? DEFAULT_PROOFREADING_CONFIG,
        chapterData.customDictionary ?? [],
        bookId,
    );
```

2. Pass both to `<WritingSurface ... customWords={customDictionary} onAddToDictionary={addToDictionary} />` (both render sites if there are several — search for `<WritingSurface`).

`resources/js/components/editor/WritingSurface.tsx`: add to the props destructuring and type block:

```ts
    customWords,
    onAddToDictionary,
```
```ts
    customWords?: string[];
    onAddToDictionary?: (word: string) => void;
```

and forward them to every `<SceneEditor ... customWords={customWords} onAddToDictionary={onAddToDictionary} />`.

`resources/js/components/editor/SceneEditor.tsx`: same prop additions, forwarded into the `useChapterEditor({ ... customWords, onAddToDictionary })` call.

- [ ] **Step 4: Run — GREEN**

```bash
cd /Users/david/Workspace/manuscript && npm run build && php artisan test --compact --filter=SpellcheckTest
```

Expected: all SpellcheckTest tests PASS.

- [ ] **Step 5: Lint, typecheck, commit**

```bash
cd /Users/david/Workspace/manuscript && npm run lint && npm run types:check && npm run format && git add tests/Browser/SpellcheckTest.php resources/js/components/editor/ChapterPane.tsx resources/js/components/editor/WritingSurface.tsx resources/js/components/editor/SceneEditor.tsx && git commit -m "feat(spellcheck): popover suggestions + per-book custom dictionary

Right-click a squiggle for Hunspell suggestions; Add to Dictionary
persists to the book's custom_dictionary (single source of truth — the
Electron session dictionary is no longer involved) and clears squiggles
everywhere in the document.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: German (compounds) + toggle behavior

**Files:**
- Test: `tests/Browser/SpellcheckTest.php` (extend)

**Interfaces:**
- Consumes: everything from Tasks 1–3; German dictionary at `/dictionaries/de/de_DE.aff|.dic`; the Command Palette spellcheck toggle (label `Disable Spell Check` / `Enable Spell Check`, `resources/js/i18n/en/editor.json:40-41`).

- [ ] **Step 1: Write the German + toggle tests**

Append to `tests/Browser/SpellcheckTest.php`:

```php
it('checks German books with the German dictionary, including compounds', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['language' => 'de']);
    $chapter = $chapters[0];
    // "Haustürschlüssel" is a compound that plain word-list checkers flag;
    // real Hunspell must accept it. "falsh" is a genuine misspelling.
    $content = '<p>Der Haustürschlüssel liegt auf dem Küchentisch und das ist falsh.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(5)
        ->assertPresent('.editor-prose .spell-error')
        ->assertSeeIn('.editor-prose .spell-error', 'falsh')
        ->assertDontSeeIn('.editor-prose .spell-error', 'Haustürschlüssel');
});

it('clears all squiggles when spell check is toggled off', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $content = '<p>Another mispeled word sits here.</p>';
    $chapter->scenes()->first()->update(['content' => $content]);
    $chapter->currentVersion->update(['content' => $content]);
    $chapter->refreshContentHash();

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('.editor-prose .spell-error')
        ->keys('body', ['Meta+k'])
        ->wait(1)
        ->click('Disable Spell Check')
        ->wait(1)
        ->assertMissing('.editor-prose .spell-error');
});
```

Check how existing browser tests open the command palette (`grep -rn "Meta+k\|palette" tests/Browser/`) and mirror that idiom; adjust the shortcut if the app binds a different one (see `CommandPalette.tsx` consumers).

- [ ] **Step 2: Run**

```bash
cd /Users/david/Workspace/manuscript && npm run build && php artisan test --compact --filter=SpellcheckTest
```

Expected: PASS. The German dictionary is ~1 MB + WASM parse, hence the longer `wait(5)`. If `assertSeeIn`/`assertDontSeeIn` are unavailable in this Pest version, consult the `pest-testing` skill for the current scoped-text assertion.

If the German test fails on the compound word: verify the shipped `.aff` actually carries compound rules (`grep -c COMPOUND public/dictionaries/de/de_DE.aff` — expect > 5). If it does and Hunspell still flags the compound, report findings before changing anything — do not paper over with a bigger wait.

- [ ] **Step 3: Commit**

```bash
cd /Users/david/Workspace/manuscript && git add tests/Browser/SpellcheckTest.php && git commit -m "test(spellcheck): German compound handling and toggle-off behavior

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Teardown of the old pipeline + full verification

**Files:**
- Delete: `resources/js/extensions/SpellcheckContextMenu.ts`
- Modify: `resources/js/types/native.d.ts` (remove `SpellcheckBridge`)
- Modify: `app/Providers/NativeAppServiceProvider.php:37`

**Interfaces:**
- Consumes: nothing may still reference `SpellcheckContextMenu` or `window.Spellcheck` (Task 2 removed the only consumers).

- [ ] **Step 1: Verify nothing references the old pieces**

```bash
cd /Users/david/Workspace/manuscript && grep -rn "SpellcheckContextMenu\|window.Spellcheck" resources/js --include="*.ts" --include="*.tsx" | grep -v "SpellcheckContextMenu.ts"
```

Expected: no output. If anything appears, fix that reference first.

- [ ] **Step 2: Delete and strip**

```bash
cd /Users/david/Workspace/manuscript && git rm resources/js/extensions/SpellcheckContextMenu.ts
```

In `resources/js/types/native.d.ts`: delete the whole `interface SpellcheckBridge { ... }` block and the `Spellcheck?: SpellcheckBridge;` line inside `interface Window`.

In `app/Providers/NativeAppServiceProvider.php`, change:

```php
            ->minHeight(680)
            ->webPreferences(['spellcheck' => true]);
```

to:

```php
            ->minHeight(680);
```

Do NOT touch `scripts/nativephp-patches/files/**` or `nativephp/electron/**` — the preload `Spellcheck` bridge stays as inert dead code there; the patch surface is fragile and not worth churning (see Global Constraints).

- [ ] **Step 3: Pint + full suite**

```bash
cd /Users/david/Workspace/manuscript && vendor/bin/pint --dirty --format agent && npm run lint && npm run types:check && npm run build && php artisan test --compact
```

Expected: everything PASSES (browser suite included — remember the `public/hot` rule if a dev server is running).

- [ ] **Step 4: Commit**

```bash
cd /Users/david/Workspace/manuscript && git add resources/js/types/native.d.ts app/Providers/NativeAppServiceProvider.php && git commit -m "refactor(spellcheck): retire Chromium/Electron spellcheck integration

The WASM Hunspell pipeline owns spelling end to end; drop the spellcheck
webPreference, the window.Spellcheck bridge typing, and the old
context-menu extension. The preload patch keeps its (now inert) bridge to
avoid churning the fragile patched Electron surface.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(`git rm` already staged the deletion.)

- [ ] **Step 5: Verify end-to-end in the real app**

Invoke the `verify` skill (or at minimum `composer run dev` / `php artisan native:run` per the `run` skill) and confirm by observation: open a chapter with a known typo → squiggle visible immediately without touching the text; right-click → suggestions; add to dictionary → squiggle gone. Report what was actually observed.

---

## Execution notes for the implementer

- Tasks are strictly ordered; each leaves the app shippable.
- Task 2 Step 10 is the plan's single genuine risk gate (hunspell-asm under Vite). Everything after it is plumbing. If the gate fails after the documented alias fix, stop and surface it — the engine fallback (Nuspell/WASM) is a user-visible design decision, not something to improvise.
- Browser tests are the only automated coverage for the worker/extension pair by design: the project has no JS unit test framework, and introducing one is out of scope (dependency change requiring approval).

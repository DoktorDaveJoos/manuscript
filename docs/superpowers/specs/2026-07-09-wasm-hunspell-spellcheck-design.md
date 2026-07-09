# WASM Hunspell Spell Check — Design

**Date:** 2026-07-09
**Status:** Approved pending user review

## Problem

Spell check squiggles are unreliable. The current implementation delegates to
Chromium's built-in spellchecker (`webPreferences: ['spellcheck' => true]` +
`spellcheck` DOM attribute on the ProseMirror contenteditable). Chromium only
checks text as it is typed or focused: a freshly loaded chapter shows no
squiggles until each paragraph is touched, and ProseMirror node re-renders
silently wipe existing squiggles. There is no Chromium API to force a
full-document pass — this is unfixable from the outside.

## Decisions (agreed with user)

1. **Own the squiggles**: run our own spellcheck engine and render misspellings
   as ProseMirror decorations. Deterministic, present on load, stable across
   re-renders.
2. **Engine: real Hunspell compiled to WASM** (`hunspell-asm`), running in a
   Web Worker. Chosen over the OS spellchecker bridge because it is
   deterministic, works in Pest browser tests, behaves identically on every
   OS, obeys the book-language setting, and removes a dependency on the
   fragile patched Electron preload. (Swap-in fallback if `hunspell-asm`
   misbehaves under Vite: Nuspell/WASM or a fresh Hunspell WASM build — same
   dictionaries, same design.)
3. **Language = book language**, one dictionary, via the existing
   `LOCALE_MAP` in `resources/js/lib/languages.ts`. No auto-detection, no
   secondary dictionary (YAGNI — revisit if users ask).
4. **Swedish (`sv`, `sv_SE`) is added** to `BOOK_LANGUAGES`. Adding further
   languages = two dictionary files + one row in `BOOK_LANGUAGES`.
5. **Grammar checking is untouched** — `write-good` / `prosemirror-proofread`
   stays as-is. This replaces spelling only.

## Architecture

```
Editor mount
  └─ SpellcheckExtension (ProseMirror plugin)
       ├─ queues all text blocks on load, changed blocks on transactions
       ├─ renders misspelled ranges as inline Decorations (.spell-error)
       └─ postMessage ⇄ spellcheck worker
            └─ Hunspell WASM + {locale}.aff/.dic + custom-dictionary accept-list
```

### Components

1. **`resources/js/workers/spellcheck-worker.ts`**
   - Boots `hunspell-asm`, fetches `/dictionaries/{locale}/index.aff` +
     `index.dic` for the book's locale.
   - API (postMessage protocol):
     - `init({ locale, customWords })`
     - `check({ blockId, text })` → `{ blockId, ranges: [{from, to, word}] }`
       (offsets relative to block text)
     - `suggest({ word })` → `string[]`
     - `addWord({ word })` / `removeWord({ word })` — updates accept-list
   - Tokenizes with the existing word regex (`/[\p{L}'’]+/gu`).
   - Word-level result cache (`Map<string, boolean>`) — prose repeats words
     heavily; repeat checks are O(1).
   - Custom-dictionary words (and their case variants) are never flagged.

2. **`resources/js/extensions/SpellcheckExtension.ts`** (replaces
   `SpellcheckContextMenu.ts`)
   - On init: walks the doc, queues every textblock for checking.
   - On transaction: maps existing decorations through the mapping; re-queues
     only textblocks whose text changed.
   - Applies worker results as `Decoration.inline(from, to, { class:
     'spell-error' })`, guarded against stale results (doc version check).
   - Right-click on a decorated range → `suggest(word)` → existing
     `SpellcheckPopover` UI with replace / add-to-dictionary actions.
   - `enabledRef` toggling clears all decorations and stops queueing (same
     pattern as today).

3. **Custom dictionary — single source of truth**
   - The existing backend per-book dictionary (`useProofreading`,
     `updateCustomDictionary`) remains authoritative.
   - `addToDictionary` → backend persist (existing code) + `addWord` to the
     worker. The Electron session dictionary
     (`addWordToSpellCheckerDictionary`) is no longer used.

4. **Dictionaries**
   - Sourced from the wooorm `dictionary-*` npm packages (LibreOffice
     lineage): de, en, es, fr, it, nl, pt, sv — verified available on npm.
   - Copied at build time into `public/dictionaries/{locale}/` (a small
     script or vite static-copy step; ~0.5–2 MB per language, fetched lazily
     by the worker — only the active book's language is ever loaded).

5. **CSS** — `.spell-error { text-decoration: underline dotted; text-decoration-color: <delete token> }`
   in the editor prose styles, using the design-system `delete` color token.

### Removals

- `SpellcheckContextMenu.ts` (replaced).
- `window.Spellcheck` usage in app code; the preload bridge and
  `webPreferences(['spellcheck' => true])` can be dropped from
  `NativeAppServiceProvider` and the nativephp patch surface once nothing
  references them. The `spellcheck` DOM attribute is set to `"false"`
  permanently (kills Chromium's own flaky squiggles so they don't double up).

## Data flow

Editor mounts → extension receives book language + custom dictionary (already
available as props) → worker `init` (~100–300 ms, async; typing is never
blocked) → full-document check → decorations appear. On typing: changed block
rechecked (word cache makes this sub-ms in practice). Language change (book
settings) → worker re-`init` with new locale → full recheck.

## Error handling

- Dictionary fetch or WASM instantiation fails → spell check silently
  disables (console warning). The editor must never break.
- Book language has no shipped dictionary → spell check off, no
  wrong-language squiggles.
- Stale worker responses (doc changed since request) → dropped via version
  check.

## Testing

- **Unit (Vitest):** tokenizer word-boundary cases (apostrophes, umlauts,
  Unicode); decoration range mapping through edits; worker protocol
  reducer logic (extracted pure where possible).
- **Feature (Pest):** custom dictionary endpoints already covered — extend
  if request shape changes (it shouldn't).
- **Browser (Pest v4):** the money test — chapter containing a known
  misspelling shows a `.spell-error` decoration **on load** (the exact
  broken case today); right-click popover replaces the word; add-to-
  dictionary clears the squiggle. German umlaut/compound word sanity check.

## Risks

- `hunspell-asm` is stable but dormant (last release 2022). Mitigation:
  verify it bundles under Vite 6 + a worker early in implementation (first
  plan step); fallback engines read the same dictionaries.
- Some dictionaries are GPL-licensed data (e.g. German igerman98). Shipping
  them as loose data files alongside a proprietary app is established
  practice (LibreOffice-lineage dictionaries are shipped this way by many
  commercial apps), but flagged here because Manuscript is commercial.
- Large chapters: initial full-doc check is chunked block-by-block in the
  worker; decorations stream in per block, so worst case is squiggles
  appearing progressively over ~a second, never jank.

## Out of scope

- Grammar checking changes (write-good stays).
- Language auto-detection / multi-dictionary checking.
- LanguageTool integration (possible later opt-in "deep grammar" feature).

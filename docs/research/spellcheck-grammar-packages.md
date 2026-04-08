# Spell Check & Grammar Check Packages for TipTap/ProseMirror

Research conducted 2026-04-01. All download counts are npm weekly downloads at time of research.

---

## 1. Spell Check Libraries

### typo-js (RECOMMENDED for client-side spell check)
- **Downloads:** ~216k/week
- **What:** Hunspell-compatible spell checker in pure JavaScript
- **Client-side:** Yes -- pure JS, explicitly has `browser: { fs: false }` field, designed for browser use
- **API:** `dictionary.check(word)` returns boolean, `dictionary.suggest(word)` returns correction array
- **Dictionaries:** Loads Hunspell `.aff` + `.dic` files. Needs `dictionary-en` (~575 KB unpacked) or equivalent
- **Maintained:** Last publish 2025-08-14 (v1.3.1) -- actively maintained
- **Bundle size:** ~613 KB unpacked (library itself), plus dictionary files
- **Zero dependencies**
- **Verdict:** Most popular, zero-dep, browser-native, actively maintained. Best standalone option.

### nspell
- **Downloads:** ~41k/week
- **What:** Hunspell-like spell checker in "plain-vanilla JavaScript"
- **Client-side:** Yes -- pure JS, no native dependencies (only dep: `is-buffer`)
- **API:** `correct(word)`, `suggest(word)`, `add(word)`, `remove(word)`, `spell(word)` (returns {correct, forbidden, warn})
- **Dictionaries:** Same Hunspell `.aff` + `.dic` files as typo-js
- **Maintained:** Last publish 2022-06-22 (v2.1.5) -- stable but not actively developed
- **Bundle size:** ~42 KB unpacked (library only), plus dictionary files
- **Used by:** `retext-spell` (the retext ecosystem spell checker plugin)
- **Verdict:** Lighter than typo-js, good API, but less actively maintained. Good if using retext ecosystem.

### hunspell-asm
- **Downloads:** ~13k/week
- **What:** WebAssembly port of actual Hunspell C++ library
- **Client-side:** Yes -- runs via WASM in browser
- **Maintained:** Last publish 2022-06-18 (v4.0.2) -- appears unmaintained
- **Bundle size:** WASM binary adds significant weight
- **Verdict:** Most accurate (real Hunspell), but unmaintained and heavy. Not recommended.

### nodehun
- **Downloads:** ~1.5k/week
- **What:** Native Node.js binding for Hunspell via `node-addon-api`
- **Client-side:** NO -- requires native C++ compilation, Node.js only
- **Verdict:** Not viable for browser/Electron renderer process. Skip.

### hunspell-wasm
- **Downloads:** ~34/week
- **What:** Another WASM port of Hunspell
- **Client-side:** Yes -- WASM
- **Verdict:** Extremely low adoption, likely experimental. Skip.

### Dictionary packages (needed by typo-js and nspell)
- `dictionary-en` (~28k/wk) -- English dictionary, 575 KB unpacked, 7 files
- `dictionary-de` (~2k/wk) -- German dictionary, 1.1 MB unpacked
- These contain Hunspell `.aff` and `.dic` files that can be loaded at runtime

---

## 2. Grammar & Style Check Libraries

### write-good (RECOMMENDED for client-side style linting)
- **Downloads:** ~52k/week
- **What:** "Naive linter for English prose" -- detects style issues, not grammar
- **Detects:** Passive voice, weasel words, weak adverbs, wordiness, cliches, repeated words, weak sentence starters, lexical illusions
- **Client-side:** Yes -- pure JavaScript, zero external dependencies
- **API:** `writeGood(text)` returns `[{reason, index, offset}]`
- **Maintained:** Last publish 2022-06-29 (v1.0.8) -- stable, mature
- **Bundle size:** ~42 KB unpacked
- **Does NOT check:** Grammar, spelling, punctuation
- **Verdict:** Lightweight, focused on prose quality. Perfect complement to a spell checker for creative writing.

### retext ecosystem (RECOMMENDED for modular NLP pipeline)
- **`retext`:** ~2M/week (framework itself)
- **What:** Pluggable natural language processor (unified ecosystem). Works server, client, and Deno.
- **Client-side:** Yes -- browser compatible
- **Key plugins:**
  - `retext-spell` (13.5k/wk) -- spell checking via nspell
  - `retext-readability` (2.5k/wk) -- Flesch-Kincaid readability scores
  - `retext-simplify` (4.7k/wk) -- suggests simpler alternatives for complex phrases
  - `retext-equality` (31.6k/wk) -- warns about insensitive/inconsiderate language
  - `retext-repeated-words` (19.7k/wk) -- catches "the the" type errors
  - `retext-indefinite-article` (19.1k/wk) -- a/an usage errors
  - `retext-sentence-spacing` (10.7k/wk) -- spacing between sentences
  - `retext-profanities` (31.6k/wk) -- profane/vulgar wording
  - `retext-readability` (2.5k/wk) -- readability level checking
- **Architecture:** Parse text into syntax tree, run plugins, get annotations
- **Maintained:** Last publish 2023-09-06 -- actively maintained ecosystem
- **Verdict:** Most comprehensive option. Modular -- pick only what you need. `retext-spell` uses nspell internally so you get spell checking too. The a/an checker, repeated words, and readability plugins are especially relevant for a manuscript editor.

### alex
- **Downloads:** ~26k/week
- **What:** "Catch insensitive, inconsiderate writing"
- **Client-side:** Yes -- built on retext
- **Maintained:** Last publish 2023-08-18 (v11.0.1)
- **Verdict:** Niche -- useful for sensitivity checking but probably not core to a manuscript editor.

### textlint
- **Downloads:** ~100k/week (kernel: ~150k/week)
- **What:** "Pluggable linting tool for natural language" -- similar to ESLint but for prose
- **Client-side:** Partially -- `@textlint/kernel` is pure JS, but full `textlint` has 24 dependencies including MCP SDK
- **Maintained:** Last publish 2026-02-21 -- very actively maintained
- **Verdict:** Powerful but heavy. More suited for CI/build pipelines than in-editor real-time checking. The dependency tree is large.

---

## 3. TipTap/ProseMirror Extensions

### prosemirror-proofread (RECOMMENDED -- best integration option)
- **Downloads:** ~317/week (young package)
- **What:** ProseMirror plugin that handles the hard part -- mapping between ProseMirror document positions and plain text offsets for any spell/grammar service
- **Client-side:** Yes
- **Architecture:**
  - Service-agnostic: you provide a `generateProofreadErrors(text)` function that returns matches
  - Handles: caching, debouncing, decoration rendering (underlines), ignore lists, suggestion popups
  - Checks each ProseMirror node individually, caches results
  - Response format matches LanguageTool output by default
  - Provides `createSuggestionBox` for replacement UI
  - `createSpellCheckEnabledStore` for toggle state
- **API:** `createProofreadPlugin(debounceMS, generateErrors, createSuggestionBox, spellCheckStore, getCustomText?, useCustomCSS?)`
- **Dependencies:** Only `object-hash`, peers: prosemirror-view/model/state/changeset
- **Maintained:** Last publish 2025-11-02, actively maintained, has beta versions (0.4.0-beta.1)
- **Has React example** in demos folder
- **Bundle size:** ~53 KB unpacked
- **Verdict:** This is the glue layer you need. It solves the hardest problem (ProseMirror position mapping) and lets you plug in any backend -- typo-js for local spell check, LanguageTool API for grammar, or both.

### prosemirror-languagetool
- **Downloads:** ~15/week
- **What:** LanguageTool plugin specifically for ProseMirror
- **Version:** 0.0.1 (extremely early)
- **Maintained:** Published 2025-08-11, single version
- **Verdict:** Too new and minimal. prosemirror-proofread is more mature and flexible.

### @grammarly/editor-sdk
- **Downloads:** ~2.5k/week
- **What:** Grammarly's official writing SDK for web editors
- **Client-side:** Yes, but requires Grammarly account/API key and internet connectivity
- **License:** Apache-2.0
- **Zero npm dependencies** (loads Grammarly's service externally)
- **Maintained:** Last publish 2025-11-04
- **Verdict:** Requires internet + Grammarly subscription. Not suitable for an offline desktop app.

### No official TipTap spell/grammar extension exists
- There is no `@tiptap/extension-spellcheck` or similar
- TipTap Pro has AI features but no dedicated spell check extension
- You would wrap prosemirror-proofread as a TipTap extension

---

## 4. LanguageTool Integration

### LanguageTool overview
- Open-source grammar/style/spell checker (Java-based)
- Can be self-hosted (Java server) or used via public API (https://api.languagetool.org)
- Supports 30+ languages
- Catches: grammar errors, style issues, punctuation, spelling, and more
- Public API: free tier with limits (20 requests/minute, 10k chars/request)

### languagetool-api (npm)
- **Downloads:** ~238/week
- **What:** Simple Node.js wrapper for the LanguageTool HTTP API
- **Version:** 1.1.2 (published 2018, unmaintained)
- **Verdict:** Thin wrapper, trivially replaceable with a fetch call. The LanguageTool API is just a POST to `/v2/check`.

### DIY LanguageTool API client (RECOMMENDED approach)
The LanguageTool API is simple enough that a dedicated package is unnecessary:
```typescript
const response = await fetch('https://api.languagetool.org/v2/check', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({ text, language: 'en-US' })
});
const data = await response.json();
// data.matches[] has offset, length, message, replacements, rule info
```

### Self-hosting options for NativePHP
- **Docker:** `docker run -p 8010:8010 erikvl87/languagetool` -- simple but requires Docker
- **Embedded Java:** Bundle LanguageTool JAR -- heavy (~200 MB), needs JRE
- **Public API:** Works but requires internet, has rate limits
- **For a desktop app:** Could use the public API with a PRO API key, or proxy through your Laravel backend

---

## 5. Recommended Architecture for Manuscript

### Option A: Fully client-side (no internet required)
- **Spell check:** `typo-js` + `dictionary-en` (and `dictionary-de` etc.)
- **Style check:** `write-good` for prose quality
- **Integration:** `prosemirror-proofread` plugin wrapped as TipTap extension
- **Pros:** Works offline, fast, no API costs
- **Cons:** No grammar checking (only spelling + style), dictionaries add ~600 KB per language

### Option B: Hybrid (local spell + remote grammar)
- **Spell check:** `typo-js` for instant local spell checking
- **Grammar + style:** LanguageTool API (public or self-hosted) via `prosemirror-proofread`
- **Integration:** `prosemirror-proofread` with a composite `generateProofreadErrors` function
- **Pros:** Best accuracy for grammar, instant spell check feedback
- **Cons:** Grammar checking needs internet (or self-hosted server)

### Option C: retext pipeline (modular, all client-side)
- **Everything:** `retext` + `retext-spell` + `retext-repeated-words` + `retext-indefinite-article` + `retext-readability`
- **Integration:** `prosemirror-proofread` calling retext pipeline
- **Pros:** Highly modular, all client-side, good for creative writing (readability scores)
- **Cons:** More setup, retext-spell uses nspell (less popular than typo-js)

### Recommendation
**Option B** is the strongest choice for a manuscript/book editor:
1. Use `prosemirror-proofread` as the ProseMirror integration layer (wrap as TipTap extension)
2. Use `typo-js` for instant local spell checking (works offline, zero latency)
3. Use LanguageTool API for grammar/style checking (can be gated behind PRO license + AI toggle)
4. Optionally add `write-good` for additional prose quality hints (passive voice, weasel words)
5. The `prosemirror-proofread` plugin's `generateProofreadErrors` function can combine both sources

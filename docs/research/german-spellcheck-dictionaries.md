# German Spell-Check Dictionary Research

Research conducted 2026-04-02. Focus: solving German compound word coverage for typo-js in an Electron/NativePHP desktop app.

---

## Problem Statement

typo-js (our current spell checker) does NOT implement Hunspell's compound word algorithm. The following `.aff` directives are parsed but have **no effect**:

- `COMPOUNDFLAG`
- `COMPOUNDBEGIN` / `COMPOUNDMIDDLE` / `COMPOUNDEND`
- `COMPOUNDPERMITFLAG` / `COMPOUNDFORBIDFLAG`
- `COMPOUNDWORDMAX`, `CHECKCOMPOUND*`, etc.

This means words like "Stockwerk", "Sinuskurve", "Kopfschmerz", "Mundhohle" get flagged as misspelled because real Hunspell would compose them from roots at runtime, but typo-js cannot.

Our current mitigation: stripped `NEEDAFFIX`/`ONLYINCOMPOUND` from de_DE_frami (258k base entries) + ~327k pre-generated compounds. Still misses many compounds.

---

## 1. Pre-Expanded German Word Lists

### A. all-the-german-words (npm)
- **URL:** https://github.com/creativecouple/all-the-german-words
- **Size:** ~1,648,000 words
- **Sources:** German Wiktionary + Letterpress word list (atebits)
- **Format:** JSON array, installable via `npm install all-the-german-words`
- **Includes compounds:** Yes -- Wiktionary-sourced, so naturally contains compounds that exist as headwords
- **License:** Not specified
- **Last activity:** ~14 commits, moderately maintained
- **Verdict:** MOST PROMISING for our use case. 1.6M words is 6x our current dictionary. Being Wiktionary-sourced means real compound words are included as they appear in natural language. Can be loaded as a flat lookup set.

### B. German-Words-Library (GitHub)
- **URL:** https://github.com/Jonny-exe/German-Words-Library
- **Size:** 1,600,000 words (sources from WithEnglishWeCan/generated-german-words-full-list)
- **Format:** JSON array (single-line and multi-line versions)
- **Sources:** External repo `generated-german-words-full-list` (unclear primary source)
- **License:** GPL-3.0
- **Last update:** August 2020
- **Verdict:** Similar size to all-the-german-words but less clear provenance. GPL-3.0 license is restrictive.

### C. Leipzig Corpora Collection (Wortschatz Leipzig)
- **URL:** https://wortschatz.uni-leipzig.de/en/download/
- **Size:** Up to ~6M unique word types in deu_news_2024 corpus (36M sentences, 566M tokens)
- **Format:** Tab-delimited text files (word + frequency), downloadable as zip
- **Sources:** German news sites, Wikipedia, web crawls (corpora from 1995-2025)
- **License:** CC BY-4.0
- **Download page:** https://wortschatz.uni-leipzig.de/en/download/ (select German, then corpus)
- **Verdict:** EXCELLENT source. 6M unique word forms from news text means massive compound coverage. The `words.txt` file in each download contains tab-separated `id \t word \t frequency`. Can extract the word column as a flat list. Available in sizes from 10K to 1M sentences. The 1M-sentence news corpora would yield hundreds of thousands of unique word forms including real-world compounds.

### D. dewiki-wordrank (German Wikipedia frequency list)
- **URL:** https://github.com/gambolputty/dewiki-wordrank
- **Size:** Not documented (but German Wikipedia has millions of unique words)
- **Format:** Tab-delimited (word + frequency), compressed as result.zip
- **Sources:** German Wikipedia dump (Nov 2, 2021)
- **License:** CC-BY-SA 4.0
- **Verdict:** Good supplementary source. Wikipedia text naturally contains many compound words. Words are lowercased.

### E. german-nouns (GitHub)
- **URL:** https://github.com/gambolputty/german-nouns
- **Size:** ~100,000 nouns with grammatical properties
- **Format:** CSV with flexion data
- **Sources:** German Wiktionary (XML dump)
- **License:** CC-BY-SA 4.0
- **Special feature:** Has `parse_compound()` Python method to decompose compounds
- **Verdict:** Too small for spell-checking (100k nouns only). The compound parser is Python-only and decomposes rather than generates.

### F. German Wiktionary Frequency Lists
- **URL:** https://en.wiktionary.org/wiki/Wiktionary:Frequency_lists/German
- **Aggregated sources:**
  - 3M most frequent words from mixed web sources (2011-2021)
  - 300k words from web (GitHub)
  - 50k+ from OpenSubtitles (CC BY-SA-4.0)
  - Leipzig Corpora 10k-1M (CC BY-4.0)
- **Verdict:** The "3M most frequent words" link is the most interesting -- if it contains 3M unique word forms, it would dwarf all other sources.

### G. enz/german-wordlist (Scrabble-oriented)
- **URL:** https://github.com/enz/german-wordlist
- **Sources:** Duden Online, German Wiktionary, Scrabble Deutschland e.V., German Wikipedia
- **Format:** One word per line, UTF-8
- **License:** CC0-1.0 (public domain)
- **Last update:** mid-2024
- **Verdict:** Nice license but size unknown and follows Scrabble rules (may exclude proper nouns, compounds with hyphens, etc.).

### H. Hunspell unmunch/wordforms
- **URL:** https://github.com/hunspell/hunspell/issues/404
- **What:** Hunspell's built-in tool to expand a `.dic`+`.aff` into all valid word forms
- **Compound limitation:** unmunch does NOT generate compound words because "the list is potentially infinite"
- **Alternative:** Spylls (Python Hunspell port) has an unmunch script but also skips compounds
- **Verdict:** NOT useful for generating compound words. Only expands affixed forms from stems.

---

## 2. Alternative JS Spell-Check Libraries

### A. nspell -- DOES NOT SUPPORT COMPOUND FLAGS

**CRITICAL FINDING:** nspell's README explicitly documents compound support:

**Supported:**
- `COMPOUNDRULE` (regex-like compound patterns)
- `COMPOUNDMIN` (minimum compound word length)
- `ONLYINCOMPOUND` (flag for compound-only words)
- `NEEDAFFIX`

**NOT Supported:**
- `COMPOUNDFLAG` -- the primary flag-based compound mechanism
- `COMPOUNDBEGIN` / `COMPOUNDMIDDLE` / `COMPOUNDLAST`
- `COMPOUNDPERMITFLAG` / `COMPOUNDFORBIDFLAG`
- `COMPOUNDMORESUFFIXES` / `COMPOUNDROOT` / `COMPOUNDWORDMAX`
- All `CHECKCOMPOUND*` directives

The source code confirms this: `lib/util/form.js` contains no compound splitting logic. Only `COMPOUNDRULE` (regex-based patterns, rarely used for German) is implemented.

**Verdict:** nspell has the SAME compound word limitation as typo-js for German. Switching would gain nothing.

- **URL:** https://github.com/wooorm/nspell
- **Version:** 2.1.5 (last published 2022-06-22)
- **Maintenance:** Stable but not actively developed

### B. hunspell-asm (WASM Hunspell)
- **URL:** https://github.com/kwonoj/hunspell-asm
- **What:** WebAssembly port of real Hunspell C++ library
- **Compound support:** YES (wraps real Hunspell, so all compound rules work)
- **Version:** 4.0.2 (last published Feb 2020)
- **Status:** UNMAINTAINED (6+ years without updates)
- **ESM support:** NO -- targets ES5 CommonJS only (`dist/` is CJS)
- **Vite compatibility:** POOR -- CJS-only, no ESM, WASM loading may conflict with Vite
- **Bundle size:** Large (WASM binary)
- **Verdict:** Would solve compound words but unmaintained, CJS-only, and would require significant build config work to use with Vite. HIGH RISK.

### C. electron-hunspell
- **URL:** https://github.com/kwonoj/electron-hunspell
- **Status:** ARCHIVED and DEPRECATED (Feb 2020)
- **Reason:** "Electron provides built-in spellchecker now"
- **Verdict:** Dead project. Skip.

### D. hunspell-wasm (different from hunspell-asm)
- **Downloads:** ~34/week
- **Verdict:** Extremely low adoption, likely experimental. Skip.

### E. No other viable JS Hunspell implementations exist
There is no maintained WASM or JS implementation of Hunspell that supports compound rules and works with modern ESM/Vite tooling.

---

## 3. Electron-Specific Approaches

### A. Electron's Built-in Spellchecker (Chromium Hunspell) -- PROMISING

Electron (v12+) exposes **programmatic spell-checking APIs** in the renderer process:

```javascript
const { webFrame } = require('electron');

// Check if a word is misspelled
webFrame.isWordMisspelled('Stockwerk');  // returns boolean

// Get suggestions for a misspelled word
webFrame.getWordSuggestions('Stockwek');  // returns string[]
```

**Key details:**
- Added in Electron v12 via PR #25060 (merged Oct 2020)
- Available in the **renderer process** via `webFrame`
- Uses Chromium's real Hunspell implementation (full compound support!)
- Language configuration via `session.setSpellCheckerLanguages(['de-DE'])`
- Dictionaries auto-downloaded from Chromium CDN (or custom URL via `setSpellCheckerDictionaryDownloadURL`)
- Returns `false` (not misspelled) if no dictionary loaded

**Session-level APIs (main process):**
- `ses.setSpellCheckerLanguages(['de-DE'])` -- enable German
- `ses.availableSpellCheckerLanguages` -- list available languages
- `ses.setSpellCheckerEnabled(true/false)` -- toggle
- `ses.addWordToSpellCheckerDictionary(word)` -- custom dictionary
- `ses.removeWordFromSpellCheckerDictionary(word)`
- `ses.listWordsInSpellCheckerDictionary()` -- get custom words

**Caveats:**
- Requires Electron v12+ (need to verify NativePHP's Electron version)
- Dictionary download happens async; `isWordMisspelled` returns `false` until loaded
- On macOS, `setSpellCheckerLanguages` is a no-op (uses OS spellchecker instead)
- With context isolation, need to expose via preload + contextBridge

**Verdict:** THIS IS THE BEST SOLUTION if we can access it. It uses real Hunspell with full compound support, is maintained by the Chromium team, requires zero additional dependencies, and has a simple synchronous API perfect for our use case.

### B. webFrame.setSpellCheckProvider (custom provider)
- Allows replacing the default spellchecker with a custom one
- NOT useful for us (we want to USE the built-in checker, not replace it)

---

## 4. nodehun (Native Hunspell Binding)

- **URL:** https://github.com/Wulf/nodehun
- **Version:** v3 (latest)
- **Downloads:** ~1,636/week
- **Maintenance:** "Inactive" per Snyk analysis (Dec 2024)
- **N-API:** Yes (stability across Node versions)
- **TypeScript:** Has declaration files
- **Compound support:** YES (wraps real Hunspell)

**Key API:**
```javascript
const nodehun = new Nodehun(affBuffer, dicBuffer);
await nodehun.spell('Stockwerk');   // true/false
await nodehun.suggest('Stockwek');  // suggestions array
nodehun.spellSync('Stockwerk');     // synchronous variant
```

**Electron compatibility:**
- Runs in Node.js (main process only, NOT renderer)
- Would need IPC bridge: renderer -> main process -> nodehun -> main process -> renderer
- Native module requires rebuild for Electron's Node.js version (`electron-rebuild`)
- IPC adds latency; spell-checking generates many IPC calls during typing
- ONLYOFFICE maintains a fork: https://github.com/ONLYOFFICE/nodehun

**Verdict:** VIABLE but complex. The IPC overhead for word-by-word checking is a concern. Better than hunspell-asm for correctness, but the Electron built-in approach (Section 3A) is simpler and avoids native compilation entirely.

---

## 5. Recommended Strategy

### Option A: Electron Built-in Spellchecker (RECOMMENDED)

Use `webFrame.isWordMisspelled()` and `webFrame.getWordSuggestions()` as the primary spell-check engine.

**Pros:**
- Full Hunspell compound word support (Chromium's implementation)
- Zero additional dependencies
- Synchronous API in renderer process
- Maintained by Chromium/Electron team
- Supports German out of the box
- Custom dictionary support via session API

**Cons:**
- macOS uses OS spellchecker (which also handles German compounds well)
- Need to verify NativePHP Electron version >= 12
- Dictionary download is async on first use
- Need preload script for context isolation

**Implementation:**
1. Expose `webFrame.isWordMisspelled` and `webFrame.getWordSuggestions` via preload
2. Replace typo-js `check()` calls with `webFrame.isWordMisspelled()`
3. Replace typo-js `suggest()` calls with `webFrame.getWordSuggestions()`
4. Set language via `session.setSpellCheckerLanguages(['de-DE'])` in main process
5. Keep custom dictionary (user's added words) via `session.addWordToSpellCheckerDictionary()`

### Option B: Supplemental Word List (FALLBACK/COMPLEMENT)

If Electron's built-in checker is not accessible (NativePHP limitations, context isolation issues), supplement typo-js with a large flat word list:

1. **Primary:** all-the-german-words (1.6M words from Wiktionary)
2. **Supplement:** Leipzig Corpora Collection deu_news (extract unique words from 1M-sentence corpus)
3. **Supplement:** dewiki-wordrank (Wikipedia frequency list)
4. Load as a `Set<string>` for O(1) lookup alongside typo-js
5. Word passes if typo-js says correct OR word is in the supplemental set

**Implementation:**
1. Download all-the-german-words, extract words into flat text file
2. Download Leipzig deu_news_2024 1M corpus, extract `words.txt` column
3. Merge, deduplicate, sort -> single flat word list
4. Ship as compressed text file (~5-10 MB estimated for 2M+ unique words)
5. Load into Set at dictionary init time
6. Modify spell-check to: `!typo.check(word) && !supplementalSet.has(word)` -> misspelled

### Option C: nodehun via IPC (COMPLEX FALLBACK)

If neither Option A nor B works:
1. Install nodehun in main process
2. Create IPC handlers for spell/suggest
3. Batch word checks to reduce IPC overhead
4. Use real Hunspell with full compound support

---

## Sources

### Word Lists
- [all-the-german-words](https://github.com/creativecouple/all-the-german-words) -- 1.6M words, npm package
- [German-Words-Library](https://github.com/Jonny-exe/German-Words-Library) -- 1.6M words, JSON
- [Leipzig Corpora Collection](https://wortschatz.uni-leipzig.de/en/download/) -- up to 6M word types, CC BY-4.0
- [dewiki-wordrank](https://github.com/gambolputty/dewiki-wordrank) -- German Wikipedia word frequencies
- [german-nouns](https://github.com/gambolputty/german-nouns) -- 100k nouns with compound parser
- [Wiktionary German Frequency Lists](https://en.wiktionary.org/wiki/Wiktionary:Frequency_lists/German)
- [german-wordlist (enz)](https://github.com/enz/german-wordlist) -- Scrabble-oriented, CC0
- [Hunspell unmunch issue](https://github.com/hunspell/hunspell/issues/404)
- [Spylls unmunch script](https://gist.github.com/zverok/c574b7a9c42cc17bdc2aa396e3edd21a)

### JS Spell-Check Libraries
- [nspell](https://github.com/wooorm/nspell) -- does NOT support COMPOUNDFLAG
- [hunspell-asm](https://github.com/kwonoj/hunspell-asm) -- WASM Hunspell, unmaintained
- [electron-hunspell](https://github.com/kwonoj/electron-hunspell) -- archived/deprecated
- [typo-js issue #21](https://github.com/cfinke/Typo.js/issues/21) -- German compound handling

### Electron Spellchecker
- [Electron SpellChecker tutorial](https://www.electronjs.org/docs/latest/tutorial/spellchecker)
- [webFrame API (isWordMisspelled, getWordSuggestions)](https://www.electronjs.org/docs/latest/api/web-frame)
- [session API (spell-check methods)](https://www.electronjs.org/docs/latest/api/session)
- [PR #25060 -- Expose renderer spellcheck API](https://github.com/electron/electron/pull/25060)
- [Issue #22829 -- Expose CheckSpelling API](https://github.com/electron/electron/issues/22829)

### nodehun
- [nodehun](https://github.com/Wulf/nodehun) -- native Hunspell binding, N-API
- [nodehun npm](https://www.npmjs.com/package/nodehun)
- [ONLYOFFICE nodehun fork](https://github.com/ONLYOFFICE/nodehun)

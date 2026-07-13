# Prose Style Analysis (Stilanalyse) — Design

Date: 2026-07-10
Status: approved by user (brainstorming session)

## Problem

Writers revising a manuscript want Papyrus-Autor-style inline style analysis: filler words, word repetition, weak verbs, filter words (tell-don't-show), clichés/pleonasms, passive constructions, and rhythm/readability stats — visible as categorized markup directly in the prose.

Today the app has two partial systems:

- Hunspell WASM spellcheck (`SpellcheckExtension` + `spellcheck.worker.ts`) — all 8 supported languages, decoration-based, but spelling only.
- `ProofreadExtension` running `write-good` via `prosemirror-proofread` — covers some style checks (weasel, adverb, passive, clichés) but is **English-only**, while the user base writes in Swedish, English, German, and French.

## Decisions (agreed with user)

- **Deterministic rule engine, no AI at runtime.** AI is used only at dev time to draft rule packs. Semantic judgment calls (true tell-don't-show, grammar) stay in the existing AI editorial review.
- **Language-neutral engine + per-language JSON rule packs.** Launch packs: **de, en, fr, sv**. Languages without a pack still get the language-agnostic tier (repetition, rhythm & stats).
- **All four category groups in v1:** (1) fillers + weak verbs + filter words, (2) stem-based word repetition, (3) clichés/pleonasms + passive/construct patterns, (4) rhythm & stats.
- **Revision mode toggle, not always-on.** Opening the Style panel turns decorations on; closing turns them off. No style nagging while drafting.
- **`write-good`/`ProofreadExtension`/`prosemirror-proofread` are removed.** The English pack absorbs their checks. Dependency change approved.
- **Vitest is added as a dev dependency** for analyzer unit tests. Dependency change approved.

## Architecture

Engine runs in the existing per-language spellcheck worker (it already owns the language lifecycle, message protocol, and a loaded Hunspell instance for stemming). Full-scene analysis on toggle-on, debounced (~500 ms) re-analysis while the mode is active. No dirty-range bookkeeping — scenes are small and the work is off the main thread.

### Components

1. **Worker** (`resources/js/workers/spellcheck.worker.ts` + new `resources/js/lib/style/analyze.ts`)
   - New messages: `init-style` (load rule pack for language), `analyze` (text blocks in → findings out).
   - `analyze.ts` reuses `lib/spellcheck/tokenize.ts` and runs:
     - list checks (fillers, weak verbs, filter words) — matched against surface form **and** Hunspell stem, so `machte`/`gemacht` hit `machen`;
     - phrase checks (clichés/pleonasms);
     - regex pattern checks (passive, `würde`-constructions, `es gibt`);
     - repetition: same stem within a ~50-word window, stopwords excluded, window does **not** cross scene boundaries;
     - stats: sentence lengths, monotony, adjective density, per-language readability formula.
   - Returns `StyleFinding[]` (`{ category, from, to, word, meta? }` — `meta` carries e.g. the repetition partner range) plus one `StyleStats` object per scene.
2. **Worker client** (`resources/js/lib/spellcheck/client.ts`)
   - New `analyzeStyle(blocks)` and rule-pack loading (fetched once, cached per language). Singleton-per-language lifecycle unchanged.
3. **`StyleAnalysisExtension.ts`** (new, one per scene editor, modeled on `SpellcheckExtension`)
   - On activation: snapshot text blocks, send to worker, map findings to doc positions, decorate with `style-<category>` classes.
   - Stale results dropped by re-comparing block text (same trick as spellcheck).
   - Deactivation clears all decorations.
4. **Panel bridge + `StylePanel`**
   - Extension reports findings + stats upward (`SceneBridgeExtension` pattern); panel aggregates across the chapter's scenes.

### Removals

- `resources/js/extensions/ProofreadExtension.ts`
- `prosemirror-proofread`, `write-good` from `package.json`
- write-good-specific parts of the proofreading settings page and `SuggestionPopover` usage tied to it

## Rule packs

One JSON per language: `public/style-packs/{lang}.json`, copied at build time like dictionaries (extend `scripts/copy-dictionaries.mjs` or sibling script). Shape:

```json
{
  "version": 1,
  "fillers": ["eigentlich", "halt", "eben"],
  "weakVerbs": ["machen", "tun"],
  "filterWords": ["dachte", "spürte", "sah"],
  "cliches": ["geschlossene Faust"],
  "patterns": {
    "passive": "\\b(wurde|wurden|wird|werden)\\s+\\w+(t|en)\\b",
    "constructs": ["\\bes gibt\\b"]
  },
  "readability": { "formula": "amstad" }
}
```

- Launch: de, en, fr, sv. Missing pack ⇒ list/pattern categories disabled for that language; repetition + rhythm & stats always available.
- Packs drafted with AI from established writing-craft sources at dev time; user reviews de/en as domain expert. `version` field allows pack evolution without code changes.

## UI

- **Entry**: new `PanelId` `"style"` in the AccessBar + command-palette entry. Panel open = decorations on; panel closed = off. Transient session state (like the spellcheck toggle).
- **Decorations**: per-category classes styled in `app.css` with design-system tokens only (dark-mode pairs required). Visual grammar ≈ the Papyrus reference: fillers muted + soft strikethrough, repetition pairs boxed, filter words underlined, clichés boxed in the delete tone, patterns dotted.
- **Popover** (imperatively mounted, like `SpellcheckPopover`): category name, one-sentence i18n explanation, repetition partner text where applicable, actions: **Ignore this word (book)**, and **Delete word** for fillers.
- **StylePanel** (`SlidePanel` + `PanelHeader`): legend with per-category color chip + live count + eye-toggle (transient session mute); rhythm & stats block (readability score, avg sentence length, sentence-length distribution strip). Counts aggregate per chapter. Jump-to-finding: out of scope for v1.

## Config & persistence

- **`ProofreadingConfig` evolves**: `grammar_enabled` + `grammar_checks` replaced by `style_checks` (booleans: fillers, weakVerbs, filterWords, cliches, patterns, repetition, rhythm; default all on). Old stored configs map to new keys on read (weasel→fillers, adverb→fillers, cliches→cliches, passive→patterns). Settings page `proofreading.tsx` swaps write-good toggle rows for category `ToggleRow`s.
- **Targeted fix**: wire the currently-vestigial `spelling_enabled` as the spellcheck toggle's initial state.
- **Ignored words**: per-book `style_ignored_words`, stored/updated like the custom dictionary (same controller pattern, book-scoped). Any migration runs against BOTH databases (default + `database/nativephp.sqlite`).
- Panel eye-toggles are session-transient; the settings page holds persistent defaults.

## Error handling

- Rule-pack fetch failure or missing pack ⇒ list/pattern categories silently unavailable; repetition + stats still run. Panel shows which categories are inactive for the language.
- Worker/WASM init failure ⇒ same degradation posture as spellcheck today (feature unavailable, no editor breakage).
- Stale analysis results (doc changed since snapshot) are dropped, never force-applied.
- `hunspell-asm` stem support must be verified early; **fallback**: shared-prefix heuristic (first ~5 letters) for repetition and inflection matching.

## Testing

- **Vitest (new)**: analyzer unit tests — each check against known de/en fixture sentences, repetition window edge cases, stats/readability math; pack-validation suite loading every shipped `style-packs/*.json` (shape, non-empty lists, every regex compiles in JS).
- **Pest Feature**: settings controller — `style_checks` round-trip, legacy `grammar_checks` mapping on read, `style_ignored_words` endpoint (store, dedupe, per-book scoping).
- **Pest Browser**: `tests/Browser/StyleAnalysisTest.php` — seed a scene with known fillers + a repetition pair; open style panel; assert category decorations; popover → Ignore this word → decoration gone; reload → still gone. Requires `npm run build` first.
- **Guardrails**: no new controller (existing Feature test extends); migrations against both DBs; `superpowers:verification-before-completion` at PR time.

## Implementation deviations (2026-07-10, as built)

- **`hunspell-asm` has no `stem()`** (verified: `spell`/`suggest`/`addWord` only) — the fallback shipped: repetition uses the shared-prefix heuristic (≥4 letters covering ≥⅔ of the longer word), and list checks match surface forms with **inflections enumerated in the packs**.
- **All 8 languages ship a pack**: es/it/nl/pt are stopword-only (repetition + stats tier); packs gained a `stopwords` field for the repetition check.
- **Pack `patterns` is a flat array** `[{id, regex}]` instead of the nested passive/constructs shape; ids may repeat across regex variants (i18n keys by category, not pattern id).
- **`style_checks` keys are singular** (`filler`, `weakVerb`, …) matching analyzer categories 1:1, plus `rhythm`.
- **Legacy config mapping**: individual write-good check keys have no 1:1 equivalents; the only preserved intents are `spelling_enabled` and a full `grammar_enabled: false` opt-out (→ all style checks off). Everything else defaults on.
- `StyleStats` carries `readabilityFormula` since LIX reads low-is-easy while Flesch-type formulas read high-is-easy.

## Risks

- ~~`hunspell-asm` may not expose `stem()`~~ — confirmed absent; fallback shipped (see deviations).
- **Rule-pack quality for fr/sv** (no in-house domain expert) — mitigated by sourcing from established craft references and the version field for fast iteration.
- **Decoration noise with all categories on** — mitigated by revision-mode gating, per-category eye-toggles, and per-book defaults.
- **Passive-voice regexes overfire** (e.g. German `werden` as future tense) — accept imprecision in v1; patterns are pack data and can be tuned without code changes.

## Out of scope (v1)

- AI-backed checks (true tell-don't-show, grammar) — stays in editorial review.
- Packs for es, it, nl, pt (language-agnostic tier still works).
- Jump-to-finding from the panel; cross-scene repetition windows; configurable repetition window size.
- LanguageTool integration (Java sidecar rejected).

# Spell & Grammar Check

## Overview

Real-time, local-only proofreading for the TipTap editor using **typo-js** (spelling) and **write-good** (grammar/style). No AI, no network calls, no token cost. Toggleable per book with configurable write-good rules.

## Architecture

### Packages

| Package | Purpose | Size | Runtime |
|---------|---------|------|---------|
| `typo-js` | Spell checking against Hunspell dictionaries | ~50KB + ~800KB dictionary | Client-side, sync |
| `write-good` | Prose style checks (passive voice, wordiness, etc.) | ~42KB | Client-side, sync |
| `prosemirror-proofread` | ProseMirror integration layer — handles text extraction, position mapping, decorations, suggestion UI | ~15KB | Client-side |

### How It Works

```
User types in TipTap editor
        │
        ▼
prosemirror-proofread plugin (debounced ~1s)
        │
        ▼
Extracts plain text from ProseMirror doc
        │
        ▼
generateProofreadErrors(text) callback
        ├── typo-js: check each word → spelling errors (red underline)
        └── write-good: check text → style issues (blue underline)
        │
        ▼
Returns Problem[] with offset, length, type, suggestions
        │
        ▼
prosemirror-proofread renders inline decorations
        │
        ▼
User clicks underlined word → suggestion popover
        ├── Accept suggestion → replaces text
        ├── Ignore → dismisses this instance
        └── Add to dictionary → adds to per-book custom dictionary (spelling only)
```

### Integration Layer

Wrap `prosemirror-proofread` as a TipTap extension (`ProofreadExtension`). The extension:

1. Initializes the proofread plugin with a `generateProofreadErrors` function
2. Combines results from typo-js and write-good into a unified `Problem[]`
3. Provides a suggestion popover component (React) via `createSuggestionBox`
4. Accepts a reactive `enabled` flag to toggle on/off
5. Accepts the book's custom dictionary and write-good config

## Spelling Layer (typo-js)

### Dictionary

- Bundle `dictionary-en` (Hunspell `.aff` + `.dic` files) as static assets
- Load once when the editor mounts, reuse across scenes
- Check words by splitting text on whitespace/punctuation, calling `typo.check(word)`

### Custom Dictionary (per-book)

- Stored as a JSON column `custom_dictionary` on the `books` table (array of strings)
- Auto-seeded from the book's extracted entities (characters, locations, items) when proofreading is first enabled
- Words added via "Add to dictionary" in the suggestion popover are appended to this array
- Custom dictionary words skip spell checking entirely

### Error Format

```typescript
{
  offset: number,    // position in plain text
  length: number,    // length of misspelled word
  type: 'spelling',
  suggestions: string[]  // from typo.suggest(word), capped at 5
}
```

## Grammar/Style Layer (write-good)

### Available Checks

| Key | Description | Default |
|-----|-------------|---------|
| `illusion` | Repeated words ("the the") | On |
| `so` | "So" at start of sentence | On |
| `thereIs` | "There is/are" at start of sentence | On |
| `tooWordy` | Verbose phrases | On |
| `passive` | Passive voice | Off |
| `weasel` | Vague/weak words | Off |
| `adverb` | Adverbs (really, very, extremely) | Off |
| `cliches` | Overused expressions | Off |
| `eprime` | "To-be" verbs | Off |

Default-on checks are the least controversial for fiction. Passive voice, adverbs, and weasel words are intentional tools in fiction writing — off by default, opt-in for writers who want stricter feedback.

### Configuration Storage

Stored as a JSON column `proofreading_config` on the `books` table:

```json
{
  "spelling_enabled": true,
  "grammar_enabled": true,
  "grammar_checks": {
    "illusion": true,
    "so": true,
    "thereIs": true,
    "tooWordy": true,
    "passive": false,
    "weasel": false,
    "adverb": false,
    "cliches": false,
    "eprime": false
  }
}
```

### Error Format

```typescript
{
  offset: number,
  length: number,
  type: 'grammar',
  suggestions: string[]  // write-good provides reason text, not replacements
}
```

Note: write-good returns reasons ("'very' is a weasel word") rather than replacement suggestions. The popover shows the reason text instead of clickable replacements.

## UI

### Inline Decorations

- **Red wavy underline** — spelling errors
- **Blue wavy underline** — grammar/style issues
- Rendered via `prosemirror-proofread` decoration classes: `proofread-spelling`, `proofread-grammar`

### Suggestion Popover

Appears on click of an underlined word. Small floating panel with:

- **Spelling errors**: up to 5 suggestions as clickable chips, plus "Add to dictionary" and "Ignore" buttons
- **Grammar errors**: reason text (e.g., "'There are' is wordy"), plus "Ignore" button
- Positioned near the underlined word, dismisses on click outside or Escape

### Book Settings Page — Proofreading

New settings page at `/books/{book}/settings/proofreading`, added to the settings sidebar navigation. Layout follows the prose-pass-rules pattern:

**Section 1: Spell Check**
- Master toggle: "Enable spell check" (on/off)
- Custom dictionary display: list of added words with ability to remove
- "Seed from entities" button (pulls character/location names into dictionary)

**Section 2: Grammar & Style**
- Master toggle: "Enable grammar check" (on/off)  
- Individual toggles for each write-good rule (same UI pattern as prose-pass-rules page)
- Each rule shows label + description + toggle

### Command Palette

Add a "Toggle Proofreading" command to the Editor section of the command palette. Quick on/off without navigating to settings.

## Data Model

### Migration: `add_proofreading_config_to_books_table`

Add two columns to `books`:

```php
$table->json('proofreading_config')->nullable();
$table->json('custom_dictionary')->nullable();
```

### Book Model

```php
// Casts
'proofreading_config' => 'array',
'custom_dictionary' => 'array',

// Default proofreading config
public static function defaultProofreadingConfig(): array
{
    return [
        'spelling_enabled' => true,
        'grammar_enabled' => true,
        'grammar_checks' => [
            'illusion' => true,
            'so' => true,
            'thereIs' => true,
            'tooWordy' => true,
            'passive' => false,
            'weasel' => false,
            'adverb' => false,
            'cliches' => false,
            'eprime' => false,
        ],
    ];
}
```

## API Endpoints

### Settings

- `PUT /books/{book}/settings/proofreading` — update proofreading config
- `PUT /books/{book}/settings/custom-dictionary` — update custom dictionary (add/remove words)
- `POST /books/{book}/settings/custom-dictionary/seed` — seed dictionary from extracted entities

All handled by `SettingsController` following existing patterns.

### Props

Proofreading config and custom dictionary are passed to the chapter editor page as Inertia props so the frontend has them on load. No separate API call needed during editing.

## File Structure

### New Files

```
resources/js/extensions/ProofreadExtension.ts        — TipTap extension wrapping prosemirror-proofread
resources/js/components/editor/SuggestionPopover.tsx  — React popover for suggestions
resources/js/pages/settings/book/proofreading.tsx     — Settings page
resources/js/hooks/useProofreading.ts                 — Hook managing dictionary + config state
```

### Modified Files

```
app/Models/Book.php                          — add casts + defaults
app/Http/Controllers/SettingsController.php  — add proofreading endpoints
resources/js/hooks/useChapterEditor.ts       — add ProofreadExtension to editor
resources/js/components/editor/CommandPalette.tsx — add toggle command
resources/js/layouts/SettingsLayout.tsx       — add nav item
routes/web.php                               — add routes
database/migrations/xxxx_add_proofreading... — add columns
```

## Testing

- **Unit test**: `generateProofreadErrors` returns correct spelling errors for known misspellings
- **Unit test**: `generateProofreadErrors` skips custom dictionary words
- **Unit test**: write-good checks respect enabled/disabled config
- **Feature test**: proofreading config update endpoint validates and persists
- **Feature test**: custom dictionary CRUD (add, remove, seed from entities)
- **Feature test**: proofreading settings page renders with correct toggle states

## Out of Scope

- AI-powered deep grammar checking (future enhancement)
- Multi-language support (English only for now)
- Auto-correct / auto-replace on type
- Chapter-level batch grammar check (was discussed, deferred in favor of real-time)

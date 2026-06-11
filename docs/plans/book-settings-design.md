# Book Settings — Design Spec (2026-06-11)

## Goal

Introduce a per-book settings area ("Book") in the sidebar directly beneath Dashboard. It absorbs the Publish page entirely and takes ownership of writing style, prose/rewriting rules, and book identity (author, genre, language, …). Writing style and prose rules stop being global: they live only on the book.

## Current state (verified)

- `books` already has per-book columns: `writing_style` (array), `writing_style_text`, `prose_pass_rules` (array), `genre`, `secondary_genres`, `author`, `language`, `custom_dictionary`, all publish metadata (`copyright_text`, `dedication_text`, `epigraph_text`, `epigraph_attribution`, `acknowledgment_text`, `about_author_text`, `also_by_text`, `klappentext`, `publisher_name`, `isbn`, `cover_image_path`, `cover_settings`) and export prefs.
- `app_settings` holds global `writing_style_text` and `prose_pass_rules` used as fallbacks via `Book::getWritingStyleDisplayAttribute()` and `Book::globalProsePassRules()`.
- Latent bug: AI agents (`ProseReviser`, `ContinueWritingAgent`) read the **global** rules via static `Book::globalProsePassRules()` / `Book::generationApplicableProsePassRules()`, ignoring `books.prose_pass_rules`.
- Publish UI: `resources/js/pages/books/publish.tsx`, rendered by `PublishController::show` at `GET /books/{book}/publish`.
- Book-scoped settings pages currently render inside the global `SettingsLayout`: `resources/js/pages/settings/book/writing-style.tsx` and `prose-pass-rules.tsx`; old book-settings GET routes redirect to `/settings`.
- Sidebar book nav order (`resources/js/components/editor/Sidebar.tsx`): Dashboard, Wiki, Plot, AI, Publish, Export.

## Decisions (user-confirmed)

1. **Global writing style + prose rules are removed from global Settings.** No fallback chain. A migration copies global values into books that lack their own, then deletes the `app_settings` keys.
2. **Sub-navigation layout** — a `BookSettingsLayout` with a left rail and one page per section (not one long form page).
3. **Sidebar label: "Book"**, placed directly beneath Dashboard. The Publish entry is removed. Global "Settings" stays in the general nav section.
4. **Backend merge: keep `PublishController` as endpoint-only.** `BookSettingsController` renders all pages; `PublishController` keeps its action endpoints but no longer renders a page.

## Navigation

- `Sidebar.tsx` book nav becomes: Dashboard, **Book**, Wiki, Plot, AI, Export.
- "Book" links to `GET /books/{book}/settings` → redirect to `/books/{book}/settings/general`.
- New `resources/js/layouts/BookSettingsLayout.tsx` modeled on `SettingsLayout` (left rail, NavItem entries, back button to the book dashboard). No book switcher — the area is already book-scoped.

## Sub-pages (all rendered by `BookSettingsController`)

| Page | Route (GET) | Contents |
|---|---|---|
| General | `/books/{book}/settings/general` | title, author, language, genre, secondary genres |
| Writing Style | `/books/{book}/settings/writing-style` | style textarea + AI regenerate (moved from `settings/book/writing-style.tsx`), book-only — no global fallback note |
| Prose Rules | `/books/{book}/settings/prose-rules` | rule toggles (moved from `settings/book/prose-pass-rules.tsx`); copy clarifies the rules drive prose pass, selection rewrite, and continue writing |
| Publishing | `/books/{book}/settings/publishing` | copyright, dedication, epigraph + attribution, acknowledgments, about author, also by, Klappentext, publisher name, ISBN, epilogue/prologue designation |
| Cover | `/books/{book}/settings/cover` | cover upload / AI generate / delete, cover settings |

Pages follow the design-system form-page conventions (`max-w-[760px]`, `SectionLabel` + `Card` per section). New pages live in `resources/js/pages/books/settings/`.

## Routes & controllers

**BookSettingsController** (exists; expanded):
- `GET /books/{book}/settings` → redirect to `general`.
- `GET` for each of the five sub-pages.
- `PUT /books/{book}/settings/general` — title, author, language, genre, secondary_genres.
- `PUT /books/{book}/settings/writing-style` and `PUT /books/{book}/settings/prose-rules` (move the book-scoped update logic here from `SettingsController` / existing methods; keep `POST .../writing-style/regenerate` as is).
- Existing old GETs `/books/{book}/settings/writing-style` and `/prose-pass-rules` no longer redirect to `/settings` — `writing-style` renders the new page; `prose-pass-rules` 301s to `prose-rules`.

**PublishController** (endpoint-only):
- Remove `show`. `GET /books/{book}/publish` → redirect to `/books/{book}/settings/publishing`.
- Keep: `PUT /books/{book}/publish` (metadata), cover upload/generate/delete/serve/download, epilogue/prologue PUTs. The Publishing and Cover pages submit to these endpoints via Wayfinder actions.

**SettingsController**: remove `updateWritingStyle` and `updateProsePassRules` (+ routes `PUT /settings/writing-style`, `PUT /settings/prose-pass-rules`).

## Data model & migration

One migration (run on BOTH databases — default and `database/nativephp.sqlite`):
1. Copy `app_settings.writing_style_text` into `books.writing_style_text` for every book where it is null/empty.
2. Copy `app_settings.prose_pass_rules` into `books.prose_pass_rules` for every book where it is null.
3. Delete both keys from `app_settings`.

No schema change — data movement only.

**Book.php**:
- `getWritingStyleDisplayAttribute()` drops the `AppSetting` fallback; reads `writing_style_text`, then the legacy `writing_style` array format.
- `globalProsePassRules()` (static) → `prosePassRules()` (instance): merges `$this->prose_pass_rules` with `defaultProsePassRules()` so newly shipped rules still auto-appear.
- `generationApplicableProsePassRules()` (static) → instance, derived from `prosePassRules()`.

**AI agents**: `ProseReviser` and `ContinueWritingAgent` switch from static `Book::` calls to `$this->book->prosePassRules()` / `->generationApplicableProsePassRules()`. This fixes the latent bug where a book's own rules were ignored.

## Global Settings cleanup

- `resources/js/pages/settings/index.tsx`: remove the writing-style and prose-pass-rules sections. Proofreading config, appearance, AI providers, license, backup stay global.
- `SettingsLayout.tsx`: remove the Book Settings section and book switcher; drop the now-unused `book` prop and `books_list` usage if nothing else needs them.
- Delete `resources/js/pages/settings/book/` after the new pages exist.
- i18n: add keys for the new nav entry and pages (en/de/es); remove keys that become dead.

## Testing

- **Feature**: extend `BookSettingsControllerTest` (new pages + updates + redirects); update `PublishControllerTest` (show → redirect, endpoints unchanged); update `SettingsControllerTest` (removed endpoints return 404/405); migration behavior covered by a feature test asserting copied values and deleted keys semantics via model behavior (book-only rules, no global fallback).
- **Browser**: new `tests/Browser/BookSettingsTest.php` (new feature area: rail navigation, save a general field, toggle a prose rule).
- `tests/Unit/GuardrailsTest.php` must stay green (no auth code; controller↔test convention).
- PR-time: `superpowers:verification-before-completion` with proof of `php artisan test --compact` and `php artisan native:migrate`.

## Out of scope

- Custom dictionary (no standalone settings UI exists; editor context menu only).
- Export page/flow (stays its own nav entry).
- Proofreading config (stays global).
- AI provider configuration (stays global).

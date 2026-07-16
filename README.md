<p align="center">
  <img src="public/icon.png" alt="Manuscript" width="128" height="128">
</p>

<h1 align="center">Manuscript</h1>

<p align="center">
  <strong>The AI-powered desktop app for writing novels.</strong><br>
  Plot. Write. Revise. Publish. — All in one place, fully offline.
</p>

<p align="center">
  <a href="https://github.com/DoktorDaveJoos/manuscript/releases/latest"><img src="https://img.shields.io/github/v/release/DoktorDaveJoos/manuscript?style=flat-square&color=0EA5E9" alt="Latest Release"></a>
  <a href="#ai-providers"><img src="https://img.shields.io/badge/AI_providers-10-8B5CF6?style=flat-square" alt="AI Providers"></a>
  <img src="https://img.shields.io/badge/platform-macOS_·_Windows_·_Linux-lightgrey?style=flat-square" alt="Platform">
  <img src="https://img.shields.io/badge/license-GPL--3.0--only-22C55E?style=flat-square" alt="License: GPL-3.0-only">
</p>

---

Most writing tools either ignore craft entirely or try to write for you. Manuscript does neither.

It's built on the belief that good authors already have the instinct for story, structure, and dialogue — what they need is a tool that **catches inconsistencies, visualizes pacing, and polishes prose** without overwriting their voice.

- **Your data stays yours.** Everything lives in a local SQLite database. Copy it, back it up, delete it — your call.
- **AI is a tool, not a crutch.** Every core feature works without AI. When you enable it, AI refines your prose and reviews your structure — it never invents your story.
- **Built for craft, not content generation.** Manuscript teaches you _why_ something is a problem, not just that it is one.

## Download

Grab the latest release for your platform from the [**Releases page**](https://github.com/DoktorDaveJoos/manuscript/releases/latest) — or [build it yourself from source](#getting-started), free forever.

> New here? A **one-shot 7-day free trial** of the Pro features can be started right from the welcome screen — no account, no credit card.

---

## Features

### Writing & Editing

| Feature | Description |
|:---|:---|
| **Rich text editor** | Full-featured TipTap editor with formatting toolbar, font selection, text alignment, and keyboard shortcuts |
| **Splitscreen editor** | Write two chapters side-by-side — `⌘-click` or right-click any chapter to open in a new pane |
| **Scenes** | Break chapters into scenes, reorder with drag-and-drop |
| **Version history** | Save, restore, and compare chapter snapshots with visual diffs |
| **Accept / reject changes** | Granular control over revisions with partial acceptance |
| **Find & replace** | Manuscript-wide search with regex, case-sensitive, and whole-word matching — results with context preview |
| **Spellcheck** | Built-in spellchecker across 8 languages (English, German, Spanish, French, Italian, Dutch, Portuguese, Swedish) with correction suggestions and a per-book custom dictionary |
| **Speech input** | Dictate into any AI chat — powered by a fully local Whisper model, your audio never leaves your device |
| **Typewriter mode** | Cursor stays centered on screen for distraction-free flow |
| **Focus mode** | Fullscreen writing — nothing but your words |
| **Chapter notes** | Persistent sidebar notes with markdown, slash commands (`/todo`, `/heading`, `/callout`) |
| **Wiki panel** | Quick-reference your story bible entries while writing, without leaving the editor |
| **Chapter management** | Split chapters at the cursor, copy a whole chapter to the clipboard, track status (draft → revised → final) with sidebar bubbles |
| **Command palette** | `⌘P` to quickly access any action — toggle panels, navigate chapters, run AI tools |
| **Auto-save** | Every keystroke saved immediately with status indicator |

### Story Structure

| Feature | Description |
|:---|:---|
| **Multiple storylines** | Main plot, subplots, romance arcs, and custom storylines — interleave across chapters |
| **Acts** | Organize your novel into color-coded act columns |
| **Plot board** | Visual plot point and beat management with drag-and-drop |
| **Beats** | Story beats linked to plot points, assignable to chapters |
| **Character roles** | Assign characters to plot points as protagonist, antagonist, mentor, and more |
| **Plot point connections** | Map relationships between story events |
| **Status tracking** | Track beats and plot points: Planned → In Progress → Complete |

#### Plot Templates

Get started fast with proven story structures:

> **Three Act Structure** · **Five Act Structure** · **Save the Cat** · **Story Circle** · **Hero's Journey**

### Story Bible

A built-in wiki for your fictional world:

| Entry Type | Use For |
|:---|:---|
| **Characters** | Full profiles with avatars, traits, roles, relationships, and first appearance tracking |
| **Locations** | Cities, buildings, landscapes, worlds |
| **Organizations** | Factions, guilds, governments, companies |
| **Items** | Weapons, artifacts, magical objects, plot devices |
| **Lore** | History, mythology, rules of magic, cultural notes |

- Global search across all entry types
- Character role classification — Protagonist, Antagonist, Love Interest, Mentor, Ally, Rival, Cameo, Extra
- Chapter and storyline linking — track where characters appear and which storylines they belong to

### Dashboard & Analytics

| Feature | Description |
|:---|:---|
| **Writing goal tracker** | Daily targets with progress visualization |
| **365-day writing heatmap** | GitHub-style contribution graph for your writing habits |
| **Streak tracking** | Daily writing streaks with visual indicators |
| **Milestone celebrations** | Achievements for word count milestones |
| **Manuscript progress** | Visual progress bar toward your target word count |
| **Stats at a glance** | Word count, page estimates, reading time, chapter count |
| **Page count estimation** | Customizable trim sizes and typography settings |
| **NaNoWriMo mode** | Built-in National Novel Writing Month tracker |

> Word counts use a Unicode word-processor algorithm — umlauts, numbers, and CJK text count consistently across languages.

### Import

Bring your existing work into Manuscript:

| Format | Features |
|:---|:---|
| **DOCX** | Full Word document parsing with formatting preservation |
| **EPUB** | E-book import with chapter structure detection |
| **ODT** | OpenDocument Text support |
| **Markdown** | `.md` file import |
| **TXT** | Plain text with chapter auto-detection and encoding detection |

- Automatic chapter splitting with multilingual heading detection
- Interactive chapter review — approve or skip chapters before import
- Merge mode for importing into existing books
- CJK-aware word counting for Asian language manuscripts
- Unicode NFC normalization for consistent text encoding

### Book Designer

A full typesetting studio for print layouts — build your own export templates and see them as book-like bound spreads while you design:

| Control | Options |
|:---|:---|
| **Page geometry** | Trim size, bleed, and margins — with KDP / IngramSpark-compatible presets |
| **Typography** | Body and heading fonts, sizes, spacing |
| **Chapter headings** | Style and position of chapter openers, drop caps with smart punctuation handling |
| **Scene breaks** | Ornaments or whitespace, your pick |

Custom templates sit right next to the built-in ones (**Classic** · **Modern** · **Elegant**) and carry their typesetting with them — pick a template at export time and the layout is done.

### Export & Publishing

Publish-ready output in multiple formats:

| Format | Description |
|:---|:---|
| **PDF** | Print-ready with cover page, table of contents with page numbers, drop caps, scene break ornaments, and exact trim dimensions |
| **EPUB** | Standard e-book with cover image, font pairings, and scene break styles |
| **KDP EPUB** | Optimized for Amazon Kindle Direct Publishing |
| **DOCX** | Two submission layouts — the international standard manuscript format and the German *Normseite* (DIN A4, 1.5-spaced, ~30 lines with correction margin) |
| **TXT** | Clean plain text |

- Front and back matter rendered in your book's language — not English-only
- Bleed modes (all edges vs. outer-only) for print-on-demand services
- Export settings persist per book

#### Publish Page

Manage your book's publishing metadata in one place:

- **Cover** — Upload your own or create one with the built-in cover creator
- **Metadata** — ISBN, subtitle, primary and secondary genres
- **Front matter** — Title page, copyright, dedication, epigraph with attribution, acknowledgments
- **Back matter** — About the author, epilogue, and configurable end sections
- **Live PDF preview** — See your formatted book before downloading
- **Native save dialog** — Export directly to your filesystem

### Text Normalization

Built-in formatter to clean up your manuscript in one click:

| Rule | What It Does |
|:---|:---|
| **Smart quotes** | Straight quotes → curly quotes |
| **Dashes** | Standardize em-dashes and en-dashes |
| **Ellipsis** | Normalize ellipsis characters |
| **Dialogue** | Fix dialogue formatting |
| **Paragraphs** | Clean up paragraph spacing |
| **Whitespace** | Remove extra whitespace |
| **Unicode NFC** | Normalize Unicode characters from imports |

> Preview changes before applying — works on full books or individual chapters.

### Backup & Recovery

- **Local backup** — encrypted on-device database backup with one-click export, import, and revert
- **Trash** — soft-delete chapters, scenes, and storylines; browse deleted items and restore with one click
- **Migration safety** — the database is snapshotted before schema updates and restored automatically if anything fails

### Languages

UI in **English · Deutsch · Español**, spellcheck in 8 languages — with a language selection dialog on first launch.

### Themes & Customization

- **Light** / **Dark** / **System** theme modes
- Font and typography preferences
- Resizable panels — sidebar, wiki, AI chat
- Collapsible sidebar and navigation groups that remember their state
- Hideable formatting toolbar
- Per-book settings — writing style, prose pass rules, proofreading language, export defaults

---

## AI Features

> **Requires a Pro license (or the free trial) + your own API key.**
> Bring your own provider — your keys, your data, your control.

### Editorial Review

The heart of Manuscript's AI: a full-manuscript editorial review — like having a professional editor read your entire book.

- Chapter-by-chapter editorial analysis with findings and severity levels
- Manuscript health scores with shared quality bands across score tiles
- Accept or dismiss individual findings, or **rewrite a chapter directly from its unresolved feedback**
- Discuss findings with AI in an editorial chat
- AI-generated chapter notes surfaced right in the editor
- Opinionated editorial persona ("Lektor") with honesty rules and anti-pattern detection — fair, encouraging, and honest, with per-section strengths
- Pre-editorial detection — tells you when your manuscript needs more work before a full review is worthwhile
- Re-review aware — the report shows how many chapters changed since the last run; failed runs are resumable

### AI Writing Tools

| Feature | Description |
|:---|:---|
| **Prose Pass** | Contextual prose refinement shown as a diff you accept or reject |
| **Prose Pass Rules** | Show-don't-tell · Dialogue tags · Filter words · Passive voice · Sentence variety · Prose tightening — customizable per book |
| **Continue Writing** | Stream AI prose straight into the editor — bridges into the text after your cursor, honors storyline continuity, and lands as a reviewable version |
| **Rewrite Selection** | Rewrite any passage with your directives taking priority, previewed before it touches your text |
| **Scene Structure** | AI proposes a scene segmentation for a chapter — review per-scene titles and word counts, accept as a new tracked version |
| **Writing Style** | AI derives your style profile from your manuscript — editable by you, offered automatically before the first prose-generating action |
| **AI Chat** | Context-aware conversation about your novel using RAG over your entire manuscript, with linked plot beats and wiki entries in context |
| **Blurb & Cover** | AI-assisted blurb writing and cover creation on the publish page |

Every AI edit is guarded: the editor locks while AI writes, every mutation carries a version guard, and nothing lands without your review.

### Plot Coach

A conversational AI story coach docked right next to your plot board:

- Board-aware foundations guidance — it knows your acts, beats, and storylines
- Batch proposals — the coach proposes chapters and beats in one pass, with an approval flow, undo, and live board sync
- Entity-aware — proposals link real characters and wiki entries, with POV characters auto-attached

### Plot Insights

| Feature | Description |
|:---|:---|
| **Plot Health** | Weighted health score across your story's structure |
| **Plot Hole Detection** | AI identifies inconsistencies in your story |
| **Tension Arc** | Generate a tension curve across your manuscript |
| **Beat Suggestions** | AI recommends what beats your structure is missing |

---

## AI Providers

Manuscript supports **10 AI providers** — use whichever fits your workflow and budget:

| Provider | Type | API Key |
|:---|:---|:---:|
| **Anthropic** (Claude) | Cloud | Required |
| **OpenAI** (GPT) | Cloud | Required |
| **Google Gemini** | Cloud | Required |
| **Groq** | Cloud | Required |
| **xAI** (Grok) | Cloud | Required |
| **DeepSeek** | Cloud | Required |
| **Mistral** | Cloud | Required |
| **OpenRouter** | Multi-model proxy | Required |
| **Azure OpenAI** | Enterprise | Required |
| **Ollama** | Local | Not needed |

> **Ollama** runs entirely on your machine — no API key, no cost, full privacy.

Setup guides walk you through getting and funding an API key per provider, and out-of-credit errors are surfaced clearly instead of failing silently.

---

## Supported Genres

From thrillers to textbooks — **36 genres** across fiction, children's books, non-fiction, and poetry:

<p align="center">

`Thriller` · `Mystery` · `Romance` · `Science Fiction` · `Fantasy` · `Horror` · `Literary Fiction` · `Historical Fiction` · `Crime` · `Adventure` · `Drama` · `Western` · `Dystopian` · `Picture Book` · `Early Reader` · `Chapter Book` · `Middle Grade` · `Young Adult` · `Non-Fiction` · `Memoir` · `Biography` · `Self-Help` · `History` · `Popular Science` · `Travel` · `True Crime` · `Essay` · `How-To Guide` · `Reference` · `Cookbook` · `Handbook` · `Academic` · `Textbook` · `Dissertation` · `Research Paper` · `Poetry`

</p>

Pick a primary genre plus secondary genres per book.

---

## Tech Stack

| Layer | Technology |
|:---|:---|
| **Desktop** | [NativePHP](https://nativephp.com) — ships as a native app on macOS, Windows, and Linux |
| **Backend** | [Laravel 13](https://laravel.com) · PHP 8.4 |
| **Frontend** | [React 19](https://react.dev) · TypeScript · [Tailwind CSS v4](https://tailwindcss.com) |
| **Bridge** | [Inertia.js v2](https://inertiajs.com) · [Wayfinder](https://github.com/laravel/wayfinder) |
| **Database** | SQLite · [sqlite-vec](https://github.com/asg017/sqlite-vec) for local vector search |
| **AI** | [Laravel AI](https://github.com/laravel/ai) · RAG with local embeddings |
| **Speech** | [whisper.cpp](https://github.com/ggerganov/whisper.cpp) — fully local speech-to-text |
| **Spellcheck** | Hunspell compiled to WebAssembly — no OS dependency |
| **Editor** | [TipTap](https://tiptap.dev) rich text editor |
| **Testing** | [Pest v4](https://pestphp.com) · PHPUnit 12 · Browser E2E tests |
| **Code Quality** | [Laravel Pint](https://laravel.com/docs/pint) · ESLint · Prettier |
| **Error Tracking** | [Sentry](https://sentry.io) (optional, off by default) |

---

## Privacy First

| | |
|:---|:---|
| **100% local** | All data stored in SQLite on your machine |
| **No cloud sync** | Your manuscript never leaves your computer |
| **Offline capable** | Write anywhere, no internet required |
| **Bring your own AI keys** | API keys stored locally, used only for your requests |
| **Local speech-to-text** | Voice input runs on-device — audio is never uploaded |
| **Optional error reporting** | Sentry integration, off by default |
| **Optional anonymous stats** | Usage analytics contain no personal data or manuscript content, and can be disabled in Settings → Privacy |

---

## Open Source & Licensing

**Manuscript is fully open source.** The complete source code is available here — build it, run it, modify it, and use it for free. Forever.

If you download a **pre-built, ready-to-run desktop app**, that's the Pro version. A one-time purchase unlocks AI features in the bundled app — and a **7-day free trial** lets you test everything first. The license is perpetual, works offline, and never phones home.

|  | Open Source (self-built) | Pro (pre-built app) |
|:---|:---:|:---:|
| Multi-book management | ✅ | ✅ |
| Splitscreen editor & focus mode | ✅ | ✅ |
| Scenes, versioning & diff view | ✅ | ✅ |
| Find & replace, spellcheck | ✅ | ✅ |
| Storylines, story bible, plot board | ✅ | ✅ |
| Dashboard, writing goals & heatmap | ✅ | ✅ |
| Import & export (PDF, EPUB, DOCX, …) | ✅ | ✅ |
| Book Designer & export templates | ✅ | ✅ |
| Publishing page & text normalization | ✅ | ✅ |
| Local encrypted backup | ✅ | ✅ |
| Editorial Review & editorial rewrite | ✅ | ✅ |
| AI writing tools (Prose Pass, Continue Writing, …) | ✅ | ✅ |
| Plot Coach & plot insights | ✅ | ✅ |
| Local speech input | ✅ | ✅ |
| Pre-built native app + auto-updates | — | ✅ |
| **Price** | **Free** | **One-time purchase** |

> AI features always require your own API key — you pay the AI providers directly for the tokens you use.

---

## Getting Started

### Prerequisites

- PHP 8.4+ with `sodium` and `sqlite3` extensions
- Node.js 18+
- Composer

### Installation

```bash
# Clone the repository
git clone https://github.com/DoktorDaveJoos/manuscript.git
cd manuscript

# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --no-interaction

# Build frontend assets
npm run build
```

### Development

```bash
# Start all services (server, queue, logs, vite)
composer run dev

# Or run as a native desktop app
composer run native:dev
```

### Running Tests

```bash
php artisan test --compact
```

### Code Style

```bash
# Fix formatting
vendor/bin/pint

# Check formatting
vendor/bin/pint --test
```

---

## Project Structure

```
app/
  Ai/                 # AI agents, prompt builders, and context management
  Console/Commands/    # Artisan commands
  Enums/               # AiProvider, Genre, VersionSource, ...
  Http/Controllers/    # Inertia page controllers
  Jobs/                # Async jobs (embeddings, editorial review, exports)
  Models/              # Eloquent models (Book, Chapter, Act, Beat, PlotPoint, ...)
  Services/            # Domain services (chunking, parsing, embeddings, export)
resources/
  js/pages/            # React pages (books, chapters, plot, canvas, settings, ...)
  js/components/       # Shared React components
  js/hooks/            # Custom React hooks
  js/i18n/             # Translation files (en, de, es)
  js/layouts/          # App and settings layouts
database/
  migrations/          # SQLite schema including sqlite-vec virtual tables
```

---

## Contributing

Contributions are welcome. If you're fixing a bug or adding a feature:

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Make sure `php artisan test` and `vendor/bin/pint --test` pass
5. Open a pull request

---

## License

Manuscript is licensed under the [GNU General Public License version 3 only](LICENSE) (`GPL-3.0-only`). Distributed copies and derivative works must comply with the GPLv3 copyleft and source-availability requirements.

GPLv3 permits commercial use and paid distribution when its terms are followed. No separate proprietary license is granted.

Third-party components remain subject to their respective licenses.

---

## Acknowledgments

Built with care in the Allgäu, Germany. Made possible by the incredible open source ecosystems of Laravel, React, NativePHP, and the many libraries this project depends on.

---

<p align="center">
  <sub>Built for writers who take their craft seriously.</sub><br>
  <sub><em>Manuscript — because your story deserves better tools.</em></sub>
</p>

<p align="center">
  <img src="public/icon.png" alt="Manuscript" width="128" height="128">
</p>

<h1 align="center">Manuscript</h1>

<p align="center">
  <strong>The AI-powered desktop app for writing novels.</strong><br>
  Plot. Write. Revise. Publish. — All in one place, fully offline.
</p>

<p align="center">
  <a href="#features"><img src="https://img.shields.io/badge/features-all_in_one-blue?style=flat-square" alt="Features"></a>
  <a href="#ai-providers"><img src="https://img.shields.io/badge/AI_providers-10+-8B5CF6?style=flat-square" alt="AI Providers"></a>
  <img src="https://img.shields.io/badge/platform-macOS_·_Windows_·_Linux-lightgrey?style=flat-square" alt="Platform">
  <img src="https://img.shields.io/badge/license-MIT-22C55E?style=flat-square" alt="License">
</p>

---

Most writing tools either ignore craft entirely or try to write for you. Manuscript does neither.

It's built on the belief that good authors already have the instinct for story, structure, and dialogue — what they need is a tool that **catches inconsistencies, visualizes pacing, and polishes prose** without overwriting their voice.

- **Your data stays yours.** Everything lives in a local SQLite database. Copy it, back it up, delete it — your call.
- **AI is a tool, not a crutch.** Every core feature works without AI. When you enable it, AI refines your prose and analyzes your structure — it never invents content.
- **Built for craft, not content generation.** Manuscript teaches you _why_ something is a problem, not just that it is one.

---

## Features

### Writing & Editing

| Feature | Description |
|:---|:---|
| **Rich text editor** | Full-featured TipTap editor with formatting toolbar, font selection, and keyboard shortcuts |
| **Splitscreen editor** | Write two chapters side-by-side — `⌘-click` or right-click any chapter to open in a new pane |
| **Scenes** | Break chapters into scenes, reorder with drag-and-drop |
| **Version history** | Save, restore, and compare chapter snapshots with visual diffs |
| **Accept / reject changes** | Granular control over revisions with partial acceptance |
| **Find & replace** | Manuscript-wide search with regex, case-sensitive, and whole-word matching — results with context preview |
| **Spellcheck** | Native OS spellchecker with multilingual dictionaries, context menu suggestions, and custom dictionary auto-seeded from your characters |
| **Typewriter mode** | Cursor stays centered on screen for distraction-free flow |
| **Focus mode** | Fullscreen writing — nothing but your words |
| **Chapter notes** | Persistent sidebar notes with markdown, slash commands (`/todo`, `/heading`, `/callout`) |
| **Wiki panel** | Quick-reference your story bible entries while writing, without leaving the editor |
| **Chapter splitting** | Split a chapter at the cursor into two |
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
- AI-powered character extraction from your manuscript
- Dual descriptions — your author notes and AI-generated descriptions displayed side by side, each clearly labeled
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
| **Health timeline** | Track manuscript health metrics over time |

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

### Export & Publishing

Publish-ready output in multiple formats with professional templates:

| Format | Description |
|:---|:---|
| **PDF** | Print-ready with cover page, drop caps, scene break ornaments, and customizable trim sizes |
| **EPUB** | Standard e-book with cover image, font pairings, and scene break styles |
| **KDP EPUB** | Optimized for Amazon Kindle Direct Publishing |
| **DOCX** | Proper manuscript submission format with formatting preservation |
| **TXT** | Clean plain text |

#### Export Templates

Choose from professionally designed templates and customize every detail:

> **Classic** · **Modern** · **Elegant** — with more coming soon

| Setting | Options |
|:---|:---|
| **Font pairings** | Curated heading + body font combinations per template |
| **Scene break styles** | 8 ornament options or simple whitespace |
| **Drop caps** | Decorative first letters with smart punctuation handling |
| **Trim sizes** | 6×9, 5.5×8.5, 8.5×11, or custom dimensions |

#### Publish Page

Manage your book's publishing metadata in one place:

- **Cover image** — Upload and manage your book cover
- **Metadata** — ISBN, subtitle, and genre
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

### Trash & Recovery

Soft-delete chapters, scenes, and storylines — browse deleted items, restore with one click, or permanently delete.

### Languages

English · Deutsch · Español

Language selection dialog on first launch — write in your preferred language from day one.

### Themes & Customization

- **Light** / **Dark** / **System** theme modes
- Font and typography preferences
- Resizable panels — sidebar, wiki, AI chat
- Collapsible sidebar for maximum writing space
- Hideable formatting toolbar
- Per-book writing style and prose pass rules

---

## AI Features

> **Requires a one-time Pro license + your own API key.**
> Bring your own provider — your keys, your data, your control.

### AI Dashboard

A central hub for all AI features — see your manuscript's health at a glance:

- **Manuscript health score** — Weighted composite across hook quality, pacing, tension, emotional arc, and craft
- **Health timeline** — Track how your manuscript improves over revisions
- **Preparation progress** — Monitor the multi-phase AI pipeline with per-chapter status
- **Analyzed chapters table** — Browse AI insights chapter by chapter
- **Bulk actions** — Run revision or beautification across your entire manuscript at once

### AI Writing Tools

| Feature | Description |
|:---|:---|
| **Prose Pass** | Contextual prose refinement shown as a diff you accept or reject |
| **Prose Pass Rules** | Show-don't-tell · Dialogue tags · Filter words · Passive voice · Sentence variety · Prose tightening — customizable per book |
| **Bulk Revision** | Run Prose Pass across your entire manuscript in one operation |
| **Text Beautification** | Grammar, clarity, and flow improvements |
| **Writing Style Extraction** | AI derives your style profile from your manuscript, editable by you |
| **AI Chat** | Context-aware conversation about your novel using RAG over your entire manuscript |
| **Character Extraction** | Automatically identify and profile characters from your chapters |
| **Next Chapter Suggestion** | AI recommends what to write next based on open threads and pacing needs |
| **Story Bible Generation** | Auto-populate your wiki from manuscript content — AI descriptions stored separately from your author notes |

### Editorial Review

A full-manuscript editorial review powered by AI — like having a professional editor read your entire book:

- Chapter-by-chapter editorial analysis with findings and severity levels
- Accept or dismiss individual findings
- Discuss findings with AI in an editorial chat
- AI-generated chapter notes surfaced in the editor
- Opinionated editorial persona ("Lektor") with honesty rules and anti-pattern detection
- Pre-editorial detection — tells you when your manuscript needs more work before a full review is worthwhile

### AI Analysis & Insights

| Feature | Description |
|:---|:---|
| **Chapter Analysis** | Tension scoring, hook quality, pacing feel, emotional shifts, sensory grounding |
| **Story Heartbeat Canvas** | Visualize tension arcs, chapter hooks, pacing rhythm, and storyline weave |
| **Plot Health Dashboard** | Weighted health score across hook quality, pacing, tension, and storyline balance |
| **Plot Hole Detection** | AI identifies inconsistencies in your story |
| **Character Consistency** | Detects behavior contradictions across chapters |
| **Chapter Ending Analysis** | Classifies endings — cliffhanger, soft hook, closed, or dead end |
| **First Chapter Audit** | When does conflict appear? Are stakes clear? |
| **Scene Audit** | Flags scenes without clear plot or character function |
| **Next Chapter Suggestion** | Based on open plot threads, pacing needs, and character absence |

### AI Usage Tracking

- Token counts (input / output) per feature
- Monthly cost breakdown
- Per-book statistics
- Feature-level usage attribution
- Per-book usage reset

### AI Preparation Pipeline

Before AI features can analyze your manuscript, a multi-phase pipeline prepares your book:

1. **Chunking** — Splits your manuscript into semantic chunks with overlap
2. **Embedding** — Generates vector embeddings for semantic search
3. **Analysis** — Analyzes each chapter for tension, pacing, hooks, and more
4. **Entity Extraction** — Identifies characters, locations, and items
5. **Story Bible Population** — Auto-fills your wiki from extracted entities
6. **Writing Style Extraction** — Derives your prose style profile

> Progress is tracked per-phase with error recovery and circuit breaker protection.

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

Configure different models for different tasks:

| Task Category | Used For |
|:---|:---|
| **Writing** | Prose pass, text beautification, editorial review |
| **Analysis** | Manuscript analysis, chapter insights |
| **Extraction** | Character and entity extraction |

---

## Supported Genres

<p align="center">

`Thriller` · `Mystery` · `Romance` · `Science Fiction` · `Fantasy` · `Horror` · `Literary Fiction` · `Historical Fiction` · `Crime` · `Adventure` · `Drama` · `Young Adult` · `Non-Fiction` · `Memoir` · `Poetry` · `Western` · `Dystopian`

</p>

---

## Tech Stack

| Layer | Technology |
|:---|:---|
| **Desktop** | [NativePHP](https://nativephp.com) — ships as a native app on macOS, Windows, and Linux |
| **Backend** | [Laravel 12](https://laravel.com) · PHP 8.4 |
| **Frontend** | [React 19](https://react.dev) · TypeScript · [Tailwind CSS v4](https://tailwindcss.com) |
| **Bridge** | [Inertia.js v2](https://inertiajs.com) · [Wayfinder](https://github.com/laravel/wayfinder) |
| **Database** | SQLite · [sqlite-vec](https://github.com/asg017/sqlite-vec) for local vector search |
| **AI** | [Laravel AI](https://github.com/laravel/ai) · RAG with local embeddings |
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
| **Optional error reporting** | Sentry integration, off by default |

---

## Open Source & Licensing

**Manuscript is fully open source.** The complete source code is available here — build it, run it, modify it, and use it for free. Forever.

If you download a **pre-built, ready-to-run desktop app**, that's the Pro version. A one-time purchase unlocks AI features in the bundled app. The license is perpetual, works offline, and never phones home.

|  | Open Source (self-built) | Pro (pre-built app) |
|:---|:---:|:---:|
| Multi-book management | ✅ | ✅ |
| Splitscreen editor & focus mode | ✅ | ✅ |
| Scenes, versioning & diff view | ✅ | ✅ |
| Find & replace, spellcheck | ✅ | ✅ |
| Storylines, story bible, plot board | ✅ | ✅ |
| Dashboard, writing goals & heatmap | ✅ | ✅ |
| Import & export (PDF, EPUB, DOCX, …) | ✅ | ✅ |
| Publishing page & export templates | ✅ | ✅ |
| Text normalization & formatting | ✅ | ✅ |
| AI Prose Pass, chat & analysis | ✅ | ✅ |
| Editorial Review | ✅ | ✅ |
| AI Dashboard & bulk revision | ✅ | ✅ |
| AI Preparation Pipeline | ✅ | ✅ |
| Pre-built native app | — | ✅ |
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
  Enums/               # AiProvider, AnalysisType, VersionSource, Genre, ...
  Http/Controllers/    # Inertia page controllers
  Jobs/                # Async jobs (analysis, embeddings, entity extraction)
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

## Acknowledgments

Built with care in the Allgäu, Germany. Made possible by the incredible open source ecosystems of Laravel, React, NativePHP, and the many libraries this project depends on.

---

<p align="center">
  <sub>Built for writers who take their craft seriously.</sub><br>
  <sub><em>Manuscript — because your story deserves better tools.</em></sub>
</p>

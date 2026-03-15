# Manuscript

**A desktop writing tool for serious authors. Offline-first. AI-assisted. You own your story.**

Manuscript is a local-first desktop application for novelists — especially self-publishers — who want professional-grade structural analysis, prose refinement, and version control without giving up ownership of their work. Everything runs on your machine. No cloud. No account. No subscriptions.

AI features are opt-in. Bring your own API key, pay the provider directly, and keep full control over every word.

---

## Why Manuscript?

Most writing tools either ignore craft entirely or try to write for you. Manuscript does neither. It's built on the belief that good authors already have the instinct for story, structure, and dialogue — what they need is a tool that catches inconsistencies, visualizes pacing, and polishes prose without overwriting their voice.

- **Your data stays yours.** Everything lives in a local SQLite database on your machine. Copy it, back it up, delete it — your call.
- **AI is a tool, not a crutch.** Every core feature works without AI. When you do enable it, AI refines your prose and analyzes your structure — it never invents content.
- **Built for craft, not content generation.** Manuscript teaches you *why* something is a problem, not just that it is one.

---

## Features

### Without AI (always free)

- **Multi-book management** — work on as many books as you want, duplicate entire books with one click
- **DOCX import** — automatic chapter splitting on Heading 1
- **Chapter editor** with full version history (restore any version with one click)
- **Scenes** — break chapters into scenes, reorder with drag-and-drop
- **Multi-storyline support** — Main, Subplot, Romance, and more — interleave storylines across chapters
- **Story Bible (Wiki)** — Characters, Locations, Organizations, Items, and Lore — searchable with avatars and relationship notes
- **Plot point tracking** — plan, track, and mark plot points as fulfilled or abandoned
- **Story Canvas** — visual overview of your novel's structure
- **Dashboard** — manuscript health at a glance: word count, page count, reading time, chapter stats, and progress tracking
- **Writing Goals & Heatmap** — set daily word count goals, track streaks, and see your 365-day writing heatmap
- **Focus Mode** — distraction-free fullscreen writing
- **Typewriter Mode** — keeps your cursor centered on screen while you write
- **Notes Panel** — attach notes to any chapter, visible alongside the editor
- **Chapter splitting** — split a chapter at cursor position into two
- **Trash & Recovery** — soft-delete chapters, scenes, and storylines with full restore
- **Normalization** — clean up formatting inconsistencies across chapters
- **Export** to DOCX and TXT — export a full book, single chapter, or entire storyline
- **Internationalization** — English and German UI

### With AI (bring your own key)

- **AI Chat** — context-aware conversation about your manuscript, powered by RAG over your entire novel
- **AI Prose Pass** — refines your writing while preserving dialogue, emotional structure, and your voice. Results shown as a diff you accept or reject.
- **Prose Pass Rules** — configurable checks for show-don't-tell, filter words, passive voice, dialogue tags, and more
- **Text Beautification** — grammar, clarity, and flow improvements separate from prose pass
- **Writing Style extraction** — automatically derives your style patterns from the manuscript, editable by you
- **AI Preparation Pipeline** — multi-phase batch indexing: chunking, embedding, analysis, entity extraction, and story bible population
- **Story Heartbeat Canvas** — four analysis lanes visualizing your novel at a glance:
  - **Tension Arc** — chapter-by-chapter tension curve with act blocks and plot point markers
  - **Chapter Hook Score** — instantly see where readers might stop turning pages
  - **Pacing Rhythm** — word count variation revealing tempo patterns
  - **Storyline Weave** — POV distribution and storyline balance
- **Thriller Health Dashboard** — weighted health score (hooks, pacing, tension, weave) with actionable next steps
- **Plot AI** — plot hole detection, beat suggestions, and tension arc generation from the plot view
- **Scene Audit** — flags scenes without clear plot or character function
- **Character Consistency** — flags behavior that contradicts established traits
- **Character Extraction** — automatically identifies characters from your chapters
- **Pacing Coach** — warns about flat stretches and suggests structural fixes
- **Chapter Ending Analysis** — classifies every chapter ending (cliffhanger, soft hook, closed, dead end)
- **First Chapter Audit** — specialized analysis: when does conflict appear? Are stakes clear?
- **Next Chapter Suggestion** — based on open plot threads, pacing needs, and character absence
- **AI Usage Dashboard** — track token usage per feature, monthly cost breakdown, and per-book statistics
- **Embeddings & Semantic Search** — RAG-powered context retrieval across your entire novel

### Supported AI Providers

Anthropic, OpenAI, Google Gemini, Groq, xAI, DeepSeek, Mistral, Ollama (local), Azure OpenAI, and OpenRouter. Configure any combination for text generation and embeddings.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Desktop | [NativePHP](https://nativephp.com) — ships as a native app on macOS, Windows, and Linux |
| Backend | [Laravel 12](https://laravel.com) with PHP 8.4 |
| Frontend | [React 19](https://react.dev) via [Inertia.js v2](https://inertiajs.com) |
| Styling | [Tailwind CSS v4](https://tailwindcss.com) |
| Editor | [Tiptap](https://tiptap.dev) rich text editor |
| Database | SQLite with [sqlite-vec](https://github.com/asg017/sqlite-vec) for local vector search |
| AI | [Laravel AI](https://github.com/laravel/ai) for multi-provider support |
| Testing | [Pest v4](https://pestphp.com) |

---

## Getting Started

### Prerequisites

- PHP 8.2+ with the `sodium` and `sqlite3` extensions
- Node.js 18+
- Composer

### Installation

```bash
git clone https://github.com/DoktorDaveJoos/manuscript.git
cd manuscript

composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
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
php artisan test
```

### Code Style

```bash
# Check
vendor/bin/pint --test

# Fix
vendor/bin/pint
```

---

## Open Source & Licensing

**Manuscript is fully open source.** The complete source code is available here — you can build it, run it, modify it, and use it for free. Forever. No restrictions on the free tier.

If you download a **pre-built, ready-to-run desktop app**, that's the Pro version. A one-time purchase of the Pro license unlocks the AI features in the bundled app. The license is perpetual, works offline, and never phones home.

This is a gift to the authors out there doing great creative work. Build it yourself and enjoy everything for free. Or grab the bundled app to support development and get going in seconds.

| | Open Source (self-built) | Pro (pre-built app) |
|---|---|---|
| Multi-book management | Yes | Yes |
| Chapter editor, scenes & versioning | Yes | Yes |
| Storylines, story bible, plot canvas | Yes | Yes |
| Dashboard, writing goals & heatmap | Yes | Yes |
| DOCX import & export | Yes | Yes |
| AI Prose Pass, Chat & analysis | Yes | Yes (with license) |
| AI Preparation Pipeline | Yes | Yes (with license) |
| Pre-built native app | - | Yes |
| **Price** | **Free** | **One-time purchase** |

AI features always require your own API key regardless of version — you pay the AI providers directly for the tokens you use.

---

## Project Structure

```
app/
  Ai/               # AI prompt builders and context management
  Console/Commands/  # Artisan commands
  Enums/             # AiProvider, AnalysisType, VersionSource
  Http/Controllers/  # Inertia page controllers
  Jobs/              # Async jobs (analysis, embeddings, character extraction)
  Models/            # Eloquent models (Book, Chapter, Chunk, License, ...)
  Services/          # Domain services (chunking, DOCX parsing, embeddings, export)
resources/
  js/pages/          # React pages (books, chapters, canvas, settings, ...)
  js/components/     # Shared React components
  js/layouts/        # App and settings layouts
database/
  migrations/        # SQLite schema including sqlite-vec virtual tables
```

---

## Contributing

Contributions are welcome. If you're fixing a bug or adding a feature, please:

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Make sure `php artisan test` and `vendor/bin/pint --test` pass
5. Open a pull request

---

## Acknowledgments

Built with love in the Allgau, Germany. Made possible by the incredible open source ecosystems of Laravel, React, NativePHP, and the many libraries this project depends on.

---

*Manuscript — because your story deserves better tools.*

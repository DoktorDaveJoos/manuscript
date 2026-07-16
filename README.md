<p align="center">
  <img src="public/icon.png" alt="Manuscript" width="128" height="128">
</p>

<h1 align="center">Manuscript</h1>

<p align="center">
  <strong>A local-first desktop app for planning, writing, revising, and publishing books.</strong><br>
  Your manuscript stays under your control. AI is optional, reviewable, and powered by your own provider account.
</p>

<p align="center">
  <a href="https://github.com/DoktorDaveJoos/manuscript/releases/latest"><img src="https://img.shields.io/github/v/release/DoktorDaveJoos/manuscript?style=flat-square&color=0EA5E9" alt="Latest release"></a>
  <a href="#ai-providers"><img src="https://img.shields.io/badge/AI_providers-8_cloud-8B5CF6?style=flat-square" alt="8 cloud AI providers"></a>
  <img src="https://img.shields.io/badge/platform-macOS_·_Windows_·_Linux-lightgrey?style=flat-square" alt="macOS, Windows, and Linux">
  <img src="https://img.shields.io/badge/license-PolyForm_Noncommercial-22C55E?style=flat-square" alt="PolyForm Noncommercial 1.0.0">
</p>

---

Manuscript brings the full novel workflow into one desktop application: draft in a focused editor, organize scenes and storylines, maintain a story bible, review structure, and export publication-ready files.

The application stores books in a local SQLite database. Core writing and organization features work without an internet connection. Cloud AI tools are optional and only run after you configure a supported provider with your own API key.

## Download

Download the latest desktop build from the [Releases page](https://github.com/DoktorDaveJoos/manuscript/releases/latest).

The desktop app includes a one-time, 7-day trial. After the trial, an active Manuscript license is required. AI usage is separate: requests are billed directly by the provider connected in Settings.

## Features

### Writing and editing

- Rich-text TipTap editor with formatting, keyboard shortcuts, typewriter mode, focus mode, and automatic saving
- Split-screen editing for working with two chapters side by side
- Chapter and scene organization with drag-and-drop ordering
- Chapter snapshots, visual diffs, restore, accept, reject, and partial acceptance
- Manuscript-wide find and replace with regex, case-sensitive, and whole-word modes
- Chapter notes, book notes, command palette, and in-editor wiki panel
- Built-in Hunspell spellcheck for 26 book languages, with a custom dictionary per book
- Local speech-to-text for AI compose fields using Whisper

### Planning and story structure

- Multiple storylines with configurable types and reading-order interleaving
- Plot board with acts, plot points, beats, statuses, chapter links, and connections
- Three Act, Five Act, Save the Cat, Story Circle, and Hero's Journey templates
- Story bible entries for characters, locations, organizations, items, and lore
- Character roles, aliases, avatars, relationships, chapter links, and storyline links

### Dashboard and analysis

- Daily writing goals, streak tracking, and a 365-day activity heatmap
- Manuscript target progress and milestone celebrations
- Word count, reading time, chapter count, and page estimates
- Editorial health scores and chapter-level craft metrics after an AI review

### Import, design, and publishing

| Area                    | Supported features                                                                             |
| :---------------------- | :--------------------------------------------------------------------------------------------- |
| **Import**              | DOCX, EPUB, ODT, Markdown, and TXT with chapter detection and review before import             |
| **Book Designer**       | Trim size, margins, bleed, typography, chapter headings, drop caps, and scene breaks           |
| **Cover tools**         | Upload an existing cover or build a typographic cover with front, back, and wraparound layouts |
| **Export**              | PDF, EPUB, KDP EPUB, DOCX, and TXT                                                             |
| **Publishing metadata** | ISBN, subtitle, genres, front matter, back matter, and cover settings                          |

### Safety and recovery

- Encrypted local database backups with import and one-step rollback
- Trash and restore for deleted chapters, scenes, and storylines
- Automatic database snapshots before migrations
- Light, dark, and system themes
- UI translations in English, German, and Spanish

## AI Features

AI features require an active Manuscript license or trial and a configured provider API key.

| Feature               | What it does                                                                                                                          |
| :-------------------- | :------------------------------------------------------------------------------------------------------------------------------------ |
| **Editorial Review**  | Reviews the full manuscript chapter by chapter, reports strengths and findings, calculates health scores, and supports follow-up chat |
| **Prose Pass**        | Produces a reviewable revision diff using configurable prose rules                                                                    |
| **Continue Writing**  | Streams a continuation after the cursor and saves it as a version for review                                                          |
| **Rewrite Selection** | Rewrites a selected passage from your instructions without immediately replacing the original                                         |
| **Scene Structure**   | Suggests scene boundaries and titles for a chapter before applying them                                                               |
| **Writing Style**     | Derives an editable style profile for prose-generating tools                                                                          |
| **Book Chat**         | Answers questions using book, plot, wiki, and locally indexed manuscript context                                                      |
| **Plot Coach**        | Discusses the current board and proposes reviewable chapter and beat changes                                                          |
| **Plot Insights**     | Runs plot health, plot-hole, beat-suggestion, and tension-arc analyses                                                                |
| **Blurb Assistant**   | Helps draft and refine publishing copy                                                                                                |

AI edits remain reviewable. Manuscript uses version guards around generated changes so stale responses cannot silently overwrite newer writing.

## AI Providers

The Settings screen currently exposes these 8 cloud providers:

| Provider           | API key  |
| :----------------- | :------: |
| Anthropic (Claude) | Required |
| OpenAI             | Required |
| Google Gemini      | Required |
| Groq               | Required |
| xAI                | Required |
| DeepSeek           | Required |
| Mistral            | Required |
| OpenRouter         | Required |

Only the providers listed above are available in Settings. Local model providers and custom enterprise endpoints are not currently exposed.

Provider setup guides are built into Settings. API keys are encrypted in the local database, connection tests surface common credential and credit errors, and advanced model fields can be customized when needed.

## Local-first and privacy

| Behavior            | Details                                                                                        |
| :------------------ | :--------------------------------------------------------------------------------------------- |
| **Book storage**    | Manuscripts, settings, revisions, and indexes live in the local SQLite database                |
| **Offline writing** | Editing, planning, search, design, and other non-AI workflows work locally                     |
| **AI requests**     | The prompt and relevant manuscript context are sent to the cloud provider selected in Settings |
| **Embeddings**      | Generated through a compatible configured provider and stored locally for sqlite-vec search    |
| **Speech input**    | Whisper transcription runs on-device; recorded audio is not sent to an AI provider             |
| **Telemetry**       | Anonymous analytics and Sentry crash reporting are optional and can be disabled                |

Internet access is required for AI requests, initial license activation, update checks, and any optional telemetry you enable.

## Tech Stack

| Layer      | Technology                                          |
| :--------- | :-------------------------------------------------- |
| Desktop    | NativePHP Desktop                                   |
| Backend    | Laravel 13 and PHP 8.4                              |
| Frontend   | React 19, TypeScript, Tailwind CSS v4               |
| App bridge | Inertia.js v2 and Laravel Wayfinder                 |
| Data       | SQLite, FTS, and sqlite-vec                         |
| AI         | Laravel AI with bring-your-own-provider credentials |
| Editor     | TipTap                                              |
| Speech     | whisper.cpp                                         |
| Testing    | Pest 4, PHPUnit 12, Vitest, and browser tests       |

## Development

### Prerequisites

- PHP 8.4 with the Sodium and SQLite extensions
- Node.js 22.12 or newer
- Composer

### Setup

```bash
git clone https://github.com/DoktorDaveJoos/manuscript.git
cd manuscript

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --no-interaction
php artisan native:migrate

npm run build
```

### Run the application

```bash
# Browser development server, queue, logs, and Vite
composer run dev

# NativePHP desktop development
composer run native:dev
```

### Verification

```bash
php artisan test --compact
npm run test:js
npm run lint:check
npm run types:check
```

### Maintained coding-agent integrations

This repository maintains project instructions and tooling for:

- **Claude Code** — `CLAUDE.md`, `.claude/`, and `.mcp.json`
- **OpenAI Codex** — `AGENTS.md`, `.agents/`, and `.codex/`

Configurations for other coding agents are not maintained.

## Source and licensing

Manuscript is source-available under the [PolyForm Noncommercial License 1.0.0](LICENSE). The license permits the noncommercial uses described in its terms; commercial use requires separate written permission from the copyright holder.

The Manuscript name, logo, and branding are not licensed for third-party products or services. Third-party dependencies remain subject to their own licenses.

## Contributing

Bug reports and feature suggestions are welcome.

Code contributions require prior written approval and a contributor agreement that preserves the copyright holder's ability to issue commercial licenses. Please open an issue before submitting code.

---

<p align="center">
  <sub>Built for writers who take their craft seriously.</sub>
</p>

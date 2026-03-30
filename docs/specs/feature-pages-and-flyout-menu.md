# Feature Pages & Flyout Menu — Design Spec

**Date:** 2026-03-26
**Platform:** Lovable
**Status:** Draft
**Depends on:** Landing Page Restructure (2026-03-18)

---

## Overview

Expand the Manuscript marketing site from a single landing page to a multi-page feature showcase. Each of the 5 core feature pillars gets its own dedicated page with deep visual storytelling. A rich mega-menu flyout replaces the current nav links, selling features at first glance.

### What changes

1. **New navbar** — simplified to `Features ▾ · Pricing · [Download]`
2. **Mega-menu flyout** — 6-column layout showcasing features + comparisons
3. **6 new pages** — `/writing`, `/story-bible`, `/plot`, `/export`, `/export/templates`, `/ai`
4. **Landing page update** — deep-dive sections become teasers that link to feature pages

---

## Navbar

Replace the current multi-link navbar with:

```
┌──────────────────────────────────────────────────────┐
│  Manuscript          Features ▾    Pricing    [Download]  │
│  (logo, left)        (center/left)  (link)    (CTA, right) │
└──────────────────────────────────────────────────────┘
```

- **Manuscript** — logo/wordmark, links to `/`
- **Features** — opens mega-menu flyout on hover/click
- **Pricing** — anchor link to `/#pricing` (scrolls on landing page, navigates from feature pages)
- **Download** — CTA button, warm accent color, same as current

All other behavior (sticky, scroll blur, mobile hamburger) unchanged from current spec.

---

## Mega-Menu Flyout

### Desktop: 6-column layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│  Writing ↗         Story Bible ↗     Plot ↗                            │
│  Distraction-free   Characters &      Visual plot                       │
│    editor             locations          timeline                       │
│  Writing goals &    World-building    Story structure                   │
│    milestones         wiki               templates                     │
│  Craft metrics &    Cross-references  Tension &                        │
│    hook scores        while writing      pacing arcs                   │
│  Prose pass &       Custom            Beat sheets                      │
│    diff view          categories                                       │
│                                                                         │
│  Export ↗           AI ↗              Compare                          │
│  PDF, EPUB,         Editorial         vs Scrivener ↗                   │
│    KDP-ready          review          vs Atticus ↗                     │
│  Professional       Prose pass &      vs Vellum ↗                      │
│    book interiors     line edits      vs Dabble ↗                      │
│  Templates          Plot hole                                          │
│    gallery ↗          detection                                        │
│  One-click          Writing analysis                                   │
│    publish-ready      & AI chat                                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Link behavior

| Element | Clickable? | Target |
|---------|-----------|--------|
| Section titles (Writing, Story Bible, Plot, Export, AI) | Yes | Feature page (`/writing`, etc.) |
| Feature sub-items (descriptive text) | No | — |
| "Templates gallery" under Export | Yes | `/export/templates` |
| Compare section title | No | — |
| Competitor names (vs Scrivener, etc.) | Yes | `/vs/scrivener`, etc. |

### Design direction

- Same design system as the landing page — Playfair Display for section titles, clean sans-serif for sub-items
- Warm background (paper-white or very subtle cream), fine-line dividers between columns
- Section titles in bold with a subtle arrow or link indicator
- Sub-items in muted text, smaller size — they sell, they don't navigate
- Generous padding between columns and rows
- Subtle shadow or border on the flyout container — elegant, not heavy

### Mobile: Accordion

On mobile (hamburger menu open), the flyout becomes a vertical accordion:

- Each section (Writing, Story Bible, Plot, Export, AI, Compare) is a collapsible item
- Tap section title to expand/collapse sub-items
- Section title is also a link to the feature page (tap arrow/chevron to navigate, tap row to expand)
- Compare items are all visible links when expanded
- Smooth open/close animation

---

## URL Structure

```
/                      → Landing page (existing)
/writing               → Writing feature page
/story-bible           → Story Bible feature page
/plot                  → Plot feature page
/export                → Export feature page
/export/templates      → Templates gallery sub-page
/ai                    → AI feature page
/vs/scrivener          → Comparison page (existing)
/vs/atticus            → Comparison page (existing)
/vs/vellum             → Comparison page (existing)
/vs/dabble             → Comparison page (existing)
```

---

## Feature Page Template

All feature pages share a common structure. Same design system as the landing page — Playfair Display headlines, warm palette, literary aesthetic.

### Page layout

```
┌──────────────────────────────────────┐
│ Navbar (sticky, same as landing)     │
├──────────────────────────────────────┤
│                                      │
│ Hero Section                         │
│   Headline (Playfair, large)         │
│   Subline (1-2 sentences, warm)      │
│   Hero screenshot (full-width or     │
│     asymmetric with text)            │
│                                      │
├──────────────────────────────────────┤
│                                      │
│ Sub-feature 1                        │
│   Headline + 1-2 paragraphs          │
│   Screenshot (alternating L/R)       │
│                                      │
├──────────────────────────────────────┤
│                                      │
│ Sub-feature 2                        │
│   (reversed layout from above)       │
│                                      │
├──────────────────────────────────────┤
│                                      │
│ Sub-feature 3                        │
│   (alternating continues)            │
│                                      │
├──────────────────────────────────────┤
│                                      │
│ CTA Section                          │
│   "Start writing" + download buttons │
│                                      │
├──────────────────────────────────────┤
│ Footer (same as landing)             │
└──────────────────────────────────────┘
```

- 3-5 sub-feature sections per page
- Alternating asymmetric layout (text-left/screenshot-right, then reversed)
- Each sub-feature gets its own screenshot
- CTA at the bottom of every feature page — echo landing page download buttons
- Subtle fade-in on scroll, consistent with landing page

---

## Page 1: Writing (`/writing`)

### Hero

- **Headline:** "Where your story finds its voice"
- **Subline:** Direction — the craft of writing, the flow state, the distraction-free space where novels come alive
- **Screenshot:** The editor with a manuscript in progress, clean and immersive

### Sub-features

#### 1. Distraction-Free Editor
- Built for novels, not documents. Long-form prose with scenes, chapters, and acts.
- Clean, focused writing space that disappears when you're in the flow.
- **Screenshot:** Editor in writing mode — minimal chrome, just text and story

#### 2. Writing Goals & Milestones
- Set daily word count goals and track your streak
- Milestone celebrations when you hit targets — 10K, 25K, 50K, "The End"
- Writing activity heatmap — see your consistency over time
- Manuscript progress tracking with words-to-go countdown
- **Screenshot:** Dashboard showing writing goals, streak counter, heatmap, and milestone celebration

#### 3. Craft Metrics & Hook Scores
- 10 craft dimensions analyzed per scene: tension dynamics, emotional arc, pacing, sensory detail, information delivery, narrative density, scene purpose, hooks, consistency, plot alignment
- Hook scores (0-10) for every scene opening and closing — know exactly how your chapter grabs readers
- Manuscript health score — a single number that tracks your novel's overall quality over time
- **Screenshot:** Craft metrics panel showing hook scores, tension graph, and manuscript health

#### 4. Prose Pass & Diff View
- AI-assisted revision that respects your voice — show don't tell, dialogue cleanup, filter words, passive voice, sentence variety
- Customizable rules — enable what matters, disable what doesn't
- Diff view shows exactly what changed between drafts — revise with intention, not guesswork
- **Screenshot:** Diff view showing before/after prose pass changes highlighted

---

## Page 2: Story Bible (`/story-bible`)

### Hero

- **Headline:** "Know your world as deeply as your readers will"
- **Subline:** Direction — every character, location, and thread organized and connected. The wiki your novel deserves.
- **Screenshot:** Story Bible overview showing populated character and location entries

### Sub-features

#### 1. Characters & Locations
- Full character profiles: name, aliases, role (POV, supporting, mentioned), personality, background, motivations
- Location entries for every setting — cities, buildings, landscapes, each with type and description
- Photo/image uploads for visual reference
- Chapter appearance tracking — see exactly where each character shows up
- **Screenshot:** Character detail page with filled-out profile, image, and chapter appearances

#### 2. Organizations, Items & Lore
- Track factions, institutions, and groups that shape your world
- Catalog meaningful objects — the letter, the key, the artifact that changes everything
- Document legends, prophecies, histories, and world-building lore
- **Screenshot:** Wiki entries showing organizations and items with descriptions

#### 3. AI-Powered Extraction
- Analyze your chapters and automatically populate the Story Bible
- AI identifies characters, locations, organizations, items, and lore from your prose
- Review and edit extracted entries — AI does the tedious work, you keep control
- **Screenshot:** AI extraction results with suggested wiki entries

#### 4. Cross-References While Writing
- Access your Story Bible from the editor — never break flow to look up eye colors or timelines
- Full-text search across all entries
- Everything connected: your universe, always at your fingertips
- **Screenshot:** Editor with Story Bible panel open alongside the manuscript

---

## Page 3: Plot (`/plot`)

### Hero

- **Headline:** "See your story from above"
- **Subline:** Direction — the architecture behind the art. Visual plot planning that helps you find the holes before your readers do.
- **Screenshot:** Plot canvas showing a multi-act story with colored beats and storylines

### Sub-features

#### 1. Visual Plot Timeline
- Your entire story arc laid out on a visual canvas
- Color-coded beat types: Setup, Conflict, Turning Point, Resolution, Worldbuilding
- Beat status tracking: Planned → Fulfilled → Abandoned
- Drag-and-drop organization — move scenes, restructure acts, see what clicks
- **Screenshot:** Plot timeline with beats in different colors across multiple acts

#### 2. Story Structure Templates
- Start from proven frameworks:
  - **Three Act** — the foundation of storytelling
  - **Five Act** — classical dramatic structure
  - **Hero's Journey** — mythic story structure
  - **Save the Cat** — 15-beat screenplay structure adapted for novels
  - **Story Circle** — character-driven circular structure
- Templates create the skeleton — you fill in the story
- **Screenshot:** Plot wizard showing template selection with structure preview

#### 3. Multiple Storylines & Character Tracking
- Track parallel storylines across your novel
- Assign characters to beats with roles: key, supporting, mentioned
- See where storylines intersect and where characters carry the action
- Acts with distinct visual identity — see the shape of your novel at a glance
- **Screenshot:** Multi-storyline view showing character involvement per beat

---

## Page 4: Export (`/export`)

### Hero

- **Headline:** "From manuscript to bookshelf"
- **Subline:** Direction — the triumphant moment your manuscript becomes a real book. Professional book interiors, every format, no separate tools needed.
- **Screenshot:** Export interface showing a beautiful PDF preview of a book interior

### Sub-features

#### 1. Every Format You Need
- **EPUB** — reflowable ebook, KDP-ready out of the box
- **PDF** — print-ready with precise pagination, trim sizes, and margins
- **DOCX** — share with editors and agents
- **TXT** — universal plain text backup
- One click from manuscript to publish-ready file
- **Screenshot:** Format selection with export options

#### 2. Professional Book Interiors
- Title page, copyright, dedication, epigraph, table of contents — all auto-generated
- Acknowledgments, About the Author, Also By — complete back matter
- Running headers, page numbers, chapter headings — every detail handled
- Upload your cover image (recommended 1600 × 2560px)
- Drag-to-reorder reading order — chapters, scenes, front and back matter
- **Screenshot:** Book interior showing title page and chapter opening with professional typography

#### 3. Publish Metadata
- Fill in publisher name, ISBN, copyright text, dedication, author bio
- Everything your book needs to go live on KDP, IngramSpark, or any platform
- Front matter and back matter sections — toggle on/off, customize content
- **Screenshot:** Publish metadata form with fields filled in

#### 4. Templates → (links to `/export/templates`)
- Three professionally designed book interior templates: Classic, Modern, Elegant
- Customize fonts, scene breaks, drop caps, and more
- Preview before you export — see exactly what your readers will see
- **[Explore all templates →](/export/templates)**
- **Screenshot:** Side-by-side comparison of a chapter opening in all three templates

---

## Page 5: Templates Gallery (`/export/templates`)

### Hero

- **Headline:** "Book interiors that honor your words"
- **Subline:** Direction — professionally designed templates that make your manuscript look like it belongs on a bookshelf. Preview them all.
- **Screenshot:** Three book spreads side by side showing each template

### Template Cards

Display as a visual grid/gallery. Each template gets a card with:

#### Classic
- **Description:** Traditional serif typography for timeless storytelling. Georgia-style body text, centered chapter headings, asterisk scene breaks. The look of a book you'd find on your grandmother's shelf.
- **Previews:** Title page, chapter opening, body page, scene break
- **Details:** Serif headings · Centered titles · `* * *` scene breaks · Italic running headers

#### Modern
- **Description:** Clean, contemporary design with mixed typography. Serif body paired with sans-serif headings for a fresh, readable look. Rule scene breaks and left-aligned titles give it a modern editorial feel.
- **Previews:** Title page, chapter opening, body page, scene break
- **Details:** Sans-serif headings · Left-aligned titles · Rule scene breaks · Compact line height

#### Elegant
- **Description:** Refined and decorative for literary fiction. Ornamental flourish scene breaks, word-form chapter numbering ("Chapter One"), and drop caps that make every chapter opening feel special.
- **Previews:** Title page, chapter opening (with drop cap), body page, scene break
- **Details:** Decorative drop caps · Word-form numbering · `~❋~` flourish breaks · 2.0em titles

### Customization callout

Below the gallery, a section highlighting that all templates are customizable:
- Override fonts for body and headings
- Choose from 8 scene break styles: Asterisks, Fleuron ❧, Flourish ~❋~, Rule, Dots • • •, Dashes, Blank Space, Ornament ✦
- Toggle drop caps on/off
- Adjust for PDF trim sizes or EPUB line heights

### CTA

"Pick your template and export your book" → Download buttons

---

## Page 6: AI (`/ai`)

### Hero

- **Headline:** "Your editor, whenever you're ready"
- **Subline:** Direction — the kind of feedback you'd pay hundreds for, available at 2am when no one else is reading. Completely optional. One toggle. Off means off.
- **Screenshot:** AI panel showing feedback on a chapter

### Sub-features

#### 1. Editorial Review (the big one)
- Comprehensive manuscript analysis across **8 editorial dimensions**: Plot, Characters, Pacing, Narrative Voice, Themes, Scene Craft, Prose Style, and Chapter Notes
- Overall score out of 100 with quality assessment
- Findings categorized by severity: Critical, Warning, Suggestion
- Per-chapter notes and specific, actionable recommendations
- The kind of developmental edit that costs $1,000+ from a human editor — available in minutes
- **Screenshot:** Editorial review dashboard showing dimension scores, overall score, and findings list

#### 2. Prose Pass & Line Edits
- AI-assisted revision that respects your voice — not rewrites, refinements
- Default rules: show don't tell, dialogue tag cleanup, filter word removal, passive voice reduction, sentence variety
- Fully customizable — enable what matters to your style, disable what doesn't
- Beautify mode: restructure paragraph and dialogue formatting without changing a word
- Diff view shows every change — accept with intention, not blind faith
- **Screenshot:** Prose pass in action with diff view showing tracked changes

#### 3. Plot Hole Detection & Chapter Analysis
- Deep per-scene analysis surfacing craft metrics and findings in real-time
- 10 craft dimensions: tension, emotional arc, pacing, sensory detail, information delivery, narrative density, scene purpose, hooks, consistency, plot alignment
- Hook scores for every scene opening and closing — know if your chapter grabs and holds
- Manuscript health timeline — watch your novel improve over revisions
- **Screenshot:** Craft metrics panel with tension graph and hook scores

#### 4. AI Chat
- Ask anything about your chapter, plot, characters, style, or ideas
- Context-aware: the AI reads your chapter and knows your full manuscript
- Discuss specific editorial findings — "How do I fix this pacing issue in chapter 7?"
- Streaming responses with markdown formatting
- Like having a well-read critique partner available 24/7
- **Screenshot:** AI chat drawer open alongside the editor, mid-conversation

### Philosophy callout

A distinct section (different background, centered layout — break the alternating pattern):

> **Powerful if you want it. Invisible if you don't.**
>
> One toggle in your book settings. Off means off — no AI touches your words unless you ask. Your book, your rules.

This acknowledges writers who don't want AI and makes the opt-in nature a feature, not a caveat.

---

## Landing Page Changes

### Current state
Each feature has a full deep-dive section (2-3 paragraphs + screenshot).

### New state
Convert each deep-dive into a **teaser** that sells the click:

```
┌──────────────────────────────────────┐
│ Headline (Playfair, same as before)  │
│ 1 short paragraph (the hook)         │
│ 1 screenshot (the best one)          │
│ "Learn more →" link to feature page  │
└──────────────────────────────────────┘
```

- Keep the alternating L/R layout rhythm
- Keep the AI section's distinct background treatment
- The Feature Overview row (Write · Organize · Plot · Export · AI) now also links each pillar to its feature page
- Everything else on the landing page (Hero, Founder, Privacy, Pricing, CTA, Footer) stays unchanged

### Footer update

Add feature page links alongside comparison links:

```
Features: Writing · Story Bible · Plot · Export · Templates · AI
Compare:  vs Scrivener · vs Atticus · vs Vellum · vs Dabble
Legal:    Impressum · Datenschutz
```

---

## Screenshots Needed

Each feature page needs 3-5 high-res screenshots (2x retina, ~1200×800px). Use placeholders initially.

| Page | Screenshots needed |
|------|-------------------|
| Writing | Editor writing mode, Dashboard (goals + heatmap + milestone), Craft metrics panel, Diff view |
| Story Bible | Wiki overview, Character detail with image, Wiki entries list, Editor with Story Bible panel |
| Plot | Plot canvas with beats, Template selection wizard, Multi-storyline character view |
| Export | Export interface with PDF preview, Format selection, Book interior (title + chapter), Publish metadata form |
| Templates | Classic preview (title + chapter + body), Modern preview, Elegant preview (with drop cap), Scene break styles comparison |
| AI | Editorial review dashboard, Prose pass diff view, Craft metrics panel, AI chat drawer |

**Total: ~23 screenshots** (some can be reused across pages — e.g., craft metrics appears on both Writing and AI)

---

## SEO Considerations

Each feature page should have:
- Unique `<title>`: "Writing Tools for Novelists — Manuscript" / "AI Book Editor — Manuscript" / etc.
- Meta description targeting feature-specific searches
- Open Graph tags with a feature-specific OG image (can be the hero screenshot initially)
- H1 = page headline, H2s for sub-features
- Internal links between feature pages where relevant (e.g., Export page links to AI for prose pass)

High-value search terms per page:
- `/writing` — "novel writing software", "distraction-free writing app", "writing goals tracker"
- `/story-bible` — "story bible software", "character database for writers", "world-building tool"
- `/plot` — "plot planning software", "story structure tool", "visual plot timeline"
- `/export` — "book formatting software", "KDP formatting tool", "epub export for writers"
- `/export/templates` — "book interior templates", "KDP book templates", "self-publishing templates"
- `/ai` — "AI book editor", "AI writing feedback", "manuscript analysis tool"

---

## Implementation Notes

- All pages built in Lovable, same project as the existing landing page
- Same global design system — Playfair Display headlines, warm palette, paper texture, fine lines
- Navbar and footer are shared components — update once, applies everywhere
- Feature pages share a common layout template (hero + alternating sub-features + CTA)
- Templates gallery page is unique — uses a card grid layout instead of alternating sections
- Mobile: all pages stack vertically, screenshots above text, same as current landing page responsive behavior

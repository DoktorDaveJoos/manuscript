# Landing Page Restructure — Design Spec

**Date:** 2026-03-18
**Platform:** Lovable
**Status:** Approved

## Design Direction

- **Typography:** Playfair Display for headlines, warm serif energy
- **Aesthetic:** Paper-textured, high-class fine lines, warm and literary
- **Tone:** Writer-to-writer. Emotional, creative, honest. Not SaaS-to-customer.
- **Color:** Light mode, warm palette — think aged paper, ink, subtle gold or muted accents
- **Core narrative:** "Your novel, from first word to finished book" — Manuscript is the only tool you need

## Practical Notes

- **Screenshots:** Use placeholder images for now. Final app screenshots will be provided as high-res PNGs (2x retina). Dimensions ~1200×800px or similar aspect ratio for feature sections, ~1400×900px for the hero.
- **Founder photo:** Placeholder portrait for now — real photo will be swapped in later.
- **Language:** Page is English-only. Impressum and Datenschutz are German legal requirements (business is German-based) — those pages are in German.
- **Analytics:** Use privacy-respecting analytics only (Plausible or similar). No Google Analytics. Consistent with the privacy-first philosophy.
- **Cookie banner:** Not needed if using Plausible (no cookies). Note this explicitly.
- **SEO/Meta:** Include proper `<title>`, meta description, and Open Graph tags. Page title: "Manuscript — Your novel, from first word to finished book". OG image will be provided later (use a placeholder).
- **Download URLs:** Use placeholder `#download` links for now. Final URLs will point to direct `.dmg` downloads.
- **Comparison sub-page links in footer:** Include them from launch but link to placeholder pages with a "Coming soon" note until the full pages are built.
- **Responsive:** On mobile, feature deep-dive sections stack vertically with the screenshot above text. Feature Overview row wraps to a 2×3 or 3×2 grid. Navbar collapses to hamburger. Smooth scroll with offset for sticky navbar height.
- **Animations:** Minimal — subtle fade-in on scroll for sections. No parallax, no heavy animation. Literary and calm.
- **No testimonials for now** — the product is early. Social proof will be added later.

## Page Structure

| # | Section | Purpose |
|---|---------|---------|
| 1 | Navbar | Navigation + Download CTA |
| 2 | Hero | The promise — your novel, start to finish |
| 3 | Feature Overview | Light anchor — 5 pillars at a glance |
| 4 | Writing | Deep dive — the editor, craft metrics, prose tools |
| 5 | Story Bible | Deep dive — your world, organized |
| 6 | Plotting | Deep dive — see your story from above |
| 7 | Export | Deep dive — manuscript becomes a real book |
| 8 | AI | Deep dive — optional superpower |
| 9 | Founder's Note | Personal, real — writer to writer |
| 10 | Privacy & Trust | Your book stays yours |
| 11 | Pricing | Generous, disarming, clear |
| 12 | Final CTA | Full circle, download |
| 13 | Footer | Links, legal, comparison pages |

## Narrative Arc

1. **Promise** (Hero) — Your novel, from first word to finished book
2. **Map** (Feature Overview) — Here's everything you get: Write, Organize, Plot, Export, AI
3. **Proof** (5 feature deep dives) — Each feature section proves a piece of the promise. Alternate layout direction (text-left/text-right) to create visual rhythm. The AI section breaks the pattern slightly with a distinct background or centered layout to signal "this one is different."
4. **Soul** (Founder's Note) — Why this exists, from one writer to another
5. **Trust** (Privacy) — Your book stays yours, always
6. **Access** (Pricing) — It's free. Seriously.
7. **Action** (Final CTA) — Start writing

---

## Section Prompts for Lovable

### Prompt 1: Global Design System

```
Set up the global design system for a literary, warm landing page for a novel-writing desktop app called "Manuscript."

Design direction:
- Playfair Display for headlines (serif, editorial, literary feel)
- Clean sans-serif for body text (Inter or similar)
- Light mode with warm palette: aged paper whites, soft warm grays, near-black text (not pure black), one muted accent color (think ink blue, deep burgundy, or antique gold — something that could exist on a book cover)
- Paper-like texture or subtle background warmth
- Fine-line decorative elements — thin rules, elegant dividers
- Generous whitespace, asymmetric scale contrast (large headlines, small muted labels)
- Typography-driven design — invest in hierarchy, weight contrast, and spacing
- No gradients, no heavy shadows, no "SaaS-y" look
- Mobile responsive

The overall feel: like opening a beautifully typeset book. Warm, confident, literary.
```

### Prompt 2: Navbar

```
Create a sticky top navbar for the Manuscript landing page.

- Logo on the left ("Manuscript" in Playfair Display or a wordmark)
- Navigation links center or right: Writing, Story Bible, Plotting, Export, AI, Pricing
- These anchor-link to sections on the page
- "Download" CTA button on the far right — subtle but clear, warm accent color
- Clean, minimal, fine bottom border or no border
- On scroll: slight background blur/opacity
- Mobile: hamburger menu

Tone: elegant, bookish, not techy. Think publisher's website, not startup.
```

### Prompt 3: Hero

```
Create the hero section for the Manuscript landing page.

Headline: "Your novel, from first word to finished book" (use Playfair Display, large, confident)
Subline: One warm, emotional sentence — speak to the dream of finishing your novel and holding it in your hands. Writer-to-writer tone. Example direction: "Everything you need to write, shape, and publish the book only you can tell."

CTA: Two Mac download buttons side by side — "Download for Apple Silicon" and "Download for Intel" — styled as warm, inviting buttons (not aggressive). Small note underneath: "Free. macOS only."

Visual: App screenshot on the right side, elegantly presented — bleeding off the edge or sitting on a subtle paper-textured surface. Show the editor with a beautiful manuscript in progress.

Layout: Text left, screenshot right, asymmetric. Generous vertical padding. This section should breathe.

Tone: This is a promise to a writer, not a product pitch. Literary, warm, confident.
```

### Prompt 4: Feature Overview (Light Anchor)

```
Create a minimal feature overview section — a "table of contents" for the page.

Display 5 pillars in a single elegant row: Write · Organize · Plot · Export · AI

Each pillar gets:
- A fine-line icon or minimal illustration
- One word label
- Optional: a very short descriptor beneath (6-8 words max)

This is NOT a full feature section. It's a breath — a mental map. Like a book's table of contents page. Typographic, elegant, almost understated.

Layout: Centered, generous horizontal spacing. Maybe a thin decorative rule above and below. Playfair Display for the labels, small sans-serif for descriptors.

The visitor should glance at this and think: "Oh, this does everything."
```

### Prompt 5: Writing (Feature Deep Dive)

```
Create the first feature deep-dive section: Writing.

This is hero-level treatment — big, immersive, the heart of the app.

Headline: Something evocative about the writing experience — the craft, the flow, the distraction-free space where stories come alive. Use Playfair Display, large. Example direction: "Where your story finds its voice" or "A writing space worthy of your words."

Content (2-3 short paragraphs or bullet points, warm prose):
- The editor: clean, focused, built for long-form prose — not docs, not notes, novels
- Craft metrics: hook scores that show you how your opening grabs readers, pacing analysis
- Prose Pass & Diff View: revise with intention, see exactly what changed between drafts

Visual: Large editor screenshot showing a beautiful manuscript in progress. Consider a second smaller screenshot for craft metrics or diff view.

Layout: Asymmetric — large screenshot on one side, text on the other. Or full-width screenshot with text overlay/below.

Tone: This is the heart. Write like you're talking to a fellow writer about the moment when the words finally flow. Emotional, not technical.
```

### Prompt 6: Story Bible / Wiki (Feature Deep Dive)

```
Create the second feature deep-dive section: Story Bible.

Hero-level treatment, equal weight to Writing.

Headline: Something about knowing your world — every character, place, and thread at your fingertips. Playfair Display, large. Example direction: "Know your world as deeply as your readers will" or "Every character. Every place. Every thread."

Content (2-3 short paragraphs or bullet points, warm prose):
- Character database, locations, world-building entries — your entire universe, organized
- Everything connected: reference your bible while you write, never break context
- Never lose track of eye colors, timelines, or that side character you introduced in chapter 3

Visual: Wiki/Story Bible screenshot showing character entries or a richly filled world with entries and connections.

Layout: Reversed from the Writing section (if Writing was text-left/image-right, this is image-left/text-right). Keep the alternating rhythm.

Tone: Warm, slightly playful — this is the organizational magic that keeps creative chaos under control. The satisfaction of having everything in its place.
```

### Prompt 7: Plotting (Feature Deep Dive)

```
Create the third feature deep-dive section: Plotting.

Hero-level treatment, equal weight.

Headline: Something about seeing your story from above — the architecture behind the art. Playfair Display, large. Example direction: "See your story from above" or "The architecture behind the art."

Content (2-3 short paragraphs or bullet points, warm prose):
- Plot timeline visualization — your entire story arc laid out visually
- Story structure, acts, turning points — see where the tension builds and where it breaks
- The bird's-eye view that helps you find the holes before your readers do

Visual: Plot timeline / canvas screenshot showing a story's structure visually.

Layout: Alternating from previous section. Maintain the rhythm.

Tone: Slightly more analytical but still warm — the satisfying feeling of a story clicking into place. Like stepping back from a puzzle and seeing the picture form.
```

### Prompt 8: Export (Feature Deep Dive)

```
Create the fourth feature deep-dive section: Export.

Hero-level treatment. This is the finish line — the moment a manuscript becomes a real book.

Headline: Something triumphant about finishing — your manuscript becoming something you can hold. Playfair Display, large. Example direction: "From manuscript to bookshelf" or "The moment your book becomes real."

Content (2-3 short paragraphs or bullet points, warm prose):
- Every format: EPUB, PDF, DOCX, KDP-Ready — everything you need to publish
- Professional book interior: trim sizes, margins, chapter headings, front matter — no separate formatting tool needed
- From manuscript to publish-ready in minutes, not hours

Visual: Export interface screenshot, ideally showing a beautiful PDF preview of a book interior. Or a spread showing multiple format outputs.

Layout: Alternating rhythm continues.

Tone: Triumphant. This is the payoff. The copy should carry the emotion of finishing something — that moment every writer dreams about. There are entire products that do just this. Manuscript includes it.
```

### Prompt 9: AI (Feature Deep Dive — Optional Superpower)

```
Create the fifth and final feature deep-dive section: AI.

Hero-level treatment, but with a distinct tone — inviting, zero pressure.

Headline: Something warm and permissive — "Your editor, if you want one" or "A second pair of eyes, whenever you're ready." Playfair Display, large.

Content (2-3 short paragraphs or bullet points, warm prose):
- AI as editor, proofreader, developmental feedback partner — line edits, style suggestions, plot hole detection
- The kind of feedback you'd pay hundreds for, available at 2am when no one else is reading
- Completely optional. One toggle. Off means off — no AI touches your words unless you ask
- This is "you wrote the book, and here's a thoughtful reader whenever you're ready" — not "AI writes your book"

Visual: Screenshot showing AI feedback in action — the chat drawer or inline suggestions.

Layout: This section could break the alternating pattern slightly — maybe centered, or with a different background tone to mark it as special/distinct from the previous four.

Tone: Respectful, honest. Acknowledge that some writers don't want AI anywhere near their work — and that's fine. For those who do, it's powerful. "Powerful if you want it. Invisible if you don't."
```

### Prompt 10: Founder's Note

```
Create a personal founder's note section.

This is intimate and short — NOT a manifesto. NOT a numbered list.

Layout:
- A real photo of the founder (placeholder for now — use a warm, approachable portrait placeholder)
- 3-4 sentences beside or below the photo
- Maybe the founder's first name and a simple label: "David, Creator of Manuscript" or just "David"

Content direction for the copy (warm, personal, honest):
- Why I built this — what frustrated me as a writer, what I wished existed
- Writer-to-writer, not founder-to-customer
- No pitch. The product already spoke for itself above.
- Something like the acknowledgments page in a book

Visual style: Simple, centered or slightly off-center. Photo shouldn't be too large. Maybe a thin decorative rule above. Warm background or the same paper texture.

Tone: Like meeting the person behind the tool. Intimate, brief, genuine.
```

### Prompt 11: Privacy & Trust — "Your Book. Yours."

```
Create a privacy and trust section.

Headline: Something about ownership and control. Playfair Display. Example direction: "Your book. Yours." or "Your words belong to you. Full stop."

Content — 4 key points, presented as elegant cards or purely typographic blocks:
1. Offline-first — works without internet, always
2. Local files — your manuscript lives on your machine, not our servers
3. No account required — download and write, that's it
4. No telemetry — your words are your business

Connect to the AI philosophy: you choose what touches your work. Same principle.

Visual: 3-4 elegant cards with fine-line icons. Or purely typographic with Playfair headlines — could be stunning. No heavy card shadows.

Layout: Grid or staggered layout. Generous spacing.

Tone: Quiet confidence. Not angry-privacy, not "we're not like Big Tech." Principled: "We believe your book belongs to you." Same warmth as the rest of the page.
```

### Prompt 12: Pricing

```
Create a pricing section.

Headline: Something generous and disarming. Playfair Display. Example direction: "Start writing. It's free." or "No trial. No catch. Just write."

Two tiers, side by side:
- Free: The full writing app — writing, organizing, plotting. No limits, no expiry, not a trial.
- Pro: AI features, advanced craft metrics, professional export options. Fair pricing.

Design:
- Two clean, elegant tier cards — not a feature-checklist wall
- The free tier should feel genuinely generous, not like a trap
- Pro tier should feel like a natural upgrade, not a gate
- Warm accent on the Pro card, but don't make Free feel like the "bad" option
- Fine-line borders, typographic hierarchy, no heavy shadows

Tone: Generous. The pricing section should feel like a gift, not a gate. This is a writer who built a tool for other writers — the pricing reflects that.
```

### Prompt 13: Final CTA

```
Create the final call-to-action section.

Headline: Bring it full circle to the hero — echo the opening promise. Playfair Display, large. Example direction: "Your story is waiting" or "Begin."

One warm closing line beneath the headline — the feeling of the last page of a good book.

CTA: Mac download buttons again — Apple Silicon + Intel. Same style as hero.

Layout: Centered, generous vertical padding, simple. Maybe a subtle decorative element — a fine rule, a small ornament.

Tone: The last page of a good book. Satisfying, complete, makes you want to start writing.
```

### Prompt 14: Footer

```
Create a dark, minimal footer.

Content:
- Manuscript logo/wordmark
- Links: Impressum, Datenschutz (Privacy Policy)
- Links to comparison pages: "Manuscript vs Scrivener", "Manuscript vs Atticus", "Manuscript vs Vellum"
- Social links if any
- Small copyright line

Design: Dark background (warm dark, not cold black), light text, minimal. Fine-line style consistent with the rest of the page.
```

### Future: Comparison Sub-Pages (SEO)

```
These are separate routes, not part of the main landing page. Create them as individual pages:

Routes:
- /vs/scrivener
- /vs/atticus
- /vs/vellum
- /vs/dabble (or other relevant competitors)

Each page:
- Targeted headline: "Manuscript vs [Competitor]"
- Honest, fair comparison — not a hit piece
- Highlight where Manuscript wins (all-in-one, free tier, privacy, offline)
- Acknowledge where the competitor is strong
- CTA back to the main landing page or direct to download
- Same design system as the main page

These pages capture "[Competitor] alternative" search traffic and redirect to Manuscript.
```

---

## Key Design Principles

1. **Literary, not SaaS** — Playfair Display, paper warmth, fine lines. This is a writing tool, not a B2B product.
2. **Writer-to-writer tone** — Emotional, creative, warm. From someone who writes to someone who writes.
3. **Show, don't tell** — Large, beautiful screenshots in every feature section. Let the product speak.
4. **Build desire, then trust, then convert** — Features first, then founder/privacy, then pricing.
5. **AI is optional** — Powerful if you want it, invisible if you don't. Never forced.
6. **Generous spirit** — The free tier is real. The pricing is fair. The privacy is principled.
7. **No competitor bashing on the main page** — Comparisons live on dedicated sub-pages for SEO.

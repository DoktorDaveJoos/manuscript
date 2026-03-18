# Export Feature Competitive Analysis

Research date: March 2026

---

## 1. Competitor Overview

| Tool | Platform | Price | Primary Role | Export Strength |
|------|----------|-------|-------------|-----------------|
| **Vellum** | Mac only | $250 (ebook) / $350 (ebook+print) | Formatter | Gold standard for beautiful output |
| **Atticus** | Cross-platform (web-based) | $147 one-time | Writer + Formatter | Best value, most features per dollar |
| **Scrivener** | Mac, Windows, iOS | $49 | Writer | Powerful but complex compile system |
| **Reedsy Studio** | Web (free) | Free | Writer + Formatter | Simplest path from draft to export |
| **Draft2Digital** | Web (free) | Free | Distributor + Formatter | Free formatting as distributor perk |
| **Ulysses** | Mac, iOS | $50/yr subscription | Writer | Clean Markdown-to-export pipeline |
| **Google Docs** | Web (free) | Free | Writer | Minimal — not a real publishing tool |

---

## 2. Export Formats by Tool

| Format | Vellum | Atticus | Scrivener | Reedsy | D2D | Ulysses | Google Docs |
|--------|--------|---------|-----------|--------|-----|---------|-------------|
| EPUB | Yes | Yes | Yes | EPUB 3 | Yes | Yes | Yes (poor quality) |
| PDF (print-ready) | Yes | Yes | Yes | Yes (CMYK) | Yes | Yes | Basic only |
| PDF (screen) | Yes | Yes | Yes | Yes | — | Yes | Yes |
| DOCX | Yes | Yes | Yes | — | — | Yes | Yes |
| MOBI | Legacy | — | Legacy | — | Legacy | — | — |
| RTF | Yes | — | Yes | — | — | — | — |
| HTML | — | — | Yes | — | — | Yes | Yes |
| Plain Text | — | — | Yes | — | — | Yes | Yes |
| Markdown | — | — | Yes (MMD) | — | — | Yes | — |

**Key insight:** MOBI is now obsolete (Amazon no longer accepts uploads). EPUB + print-ready PDF are the only two formats that matter for publishing. DOCX matters for editor handoff.

---

## 3. Templates & Styling

### Vellum
- ~26 built-in styles/themes with coordinated heading, body, ornamental break, and drop cap designs
- Limited font selection (~17 fonts) — but all are carefully curated and embedded
- Custom ornamental break and heading images supported
- Vellum 4.0 (Nov 2025) added: 4-level subheadings, heading preset fine-tuning (spacing, scale, alignment), chapter number format options (numeric, spelled out, Roman numerals)
- **Weakness:** Many authors note "all Vellum books look the same" due to limited customization

### Atticus
- 17+ customizable themes, genre-specific (Fantasy, Romance, etc.)
- 1,500+ fonts available
- Custom theme creation supported
- Callout boxes, full-bleed images
- More flexible than Vellum but output polish is slightly lower

### Scrivener
- Compile system with Section Types mapped to Section Layouts
- Highly customizable but notoriously complex — "people take courses to learn Compile"
- Can customize every aspect: fonts, margins, headers, footers, page sizes, numbering
- Front/back matter folders built into project templates
- **Weakness:** Steep learning curve; most users never master Compile

### Reedsy Studio
- 3 themes only: Reedsy (modern/nonfiction), Classic (traditional), Romance
- Very limited customization — but the output is clean and professional
- **Weakness:** Too few options for power users

### Draft2Digital
- Multiple free templates with drop caps, header graphics, flourishes, scene dividers
- Automatic chapter detection
- Consistent styling across ebook and print
- **Strength:** Completely free, good enough for many authors

### Ulysses
- Multiple built-in export styles per format (PDF/DOCX, EPUB, HTML)
- Community-contributed style library
- Markdown-based — styling is applied at export time, not during writing

---

## 4. Key Feature Comparison

### Print-Ready PDF vs Screen PDF
- **Vellum:** Generates print-ready PDFs at chosen trim size with proper bleed (0.125" default). KDP and IngramSpark compatible.
- **Atticus:** Automatic bleed handling. Exports print-ready PDF at selected trim size.
- **Reedsy:** Two PDF types — print-ready (removes links, CMYK images) and screen/digital PDF. Smart distinction.
- **Scrivener:** Can produce print PDFs but requires manual configuration of page size, margins, etc.

### Trim Sizes
- **Vellum:** Extensive list grouped by US/UK and International tabs, with "More Options" for additional sizes. Mass market paperback and large print supported.
- **Atticus:** Standard sizes (5x8, 5.5x8.5, 6x9, etc.) with click-to-select.
- **Reedsy:** 4 sizes — Trade (6x9), Digest (5.5x8.5), Reedsy Standard (5x8), Pocket (4.25x6.87).
- **Scrivener:** Fully customizable page dimensions (any size).

### Front & Back Matter

| Element | Vellum | Atticus | Scrivener | Reedsy |
|---------|--------|---------|-----------|--------|
| Title page | Auto | Auto | Template | Auto |
| Copyright page | Auto | Auto | Template | Auto (required) |
| Dedication | Yes | Yes | Template | Yes |
| Epigraph | Yes | Yes | Template | Yes |
| Table of Contents | Auto | Auto-updating | Manual/Auto | Auto |
| Foreword/Preface | Yes | Yes | Template | Yes |
| Acknowledgments | Yes | Yes | Template | Yes |
| About the Author | Yes | Yes | Template | Yes |
| Also By | Yes | Yes | Template | Yes |
| Newsletter CTA | Yes | Yes | Manual | — |

**Key insight:** Vellum and Atticus treat front/back matter as first-class structured elements with presets. Scrivener uses generic folders. Reedsy auto-generates but forces a small "made with Reedsy" credit on the copyright page.

### Chapter Heading Styles
- **Vellum:** Heading presets with alignment, spacing, scale controls. Chapter numbers as digits, words, or Roman numerals. Custom heading images supported.
- **Atticus:** Genre-themed heading styles. Customizable fonts, sizes, alignment.
- **Scrivener:** Fully customizable via Section Layouts — title prefix with auto-numbering placeholders, font/size/alignment control.
- **Reedsy:** Tied to the 3 themes — limited control.

### Scene/Section Breaks
- **Vellum:** Ornamental break carousel with multiple built-in designs. Custom break images supported. Small caps, decorative ornaments, or blank space options.
- **Atticus:** Ornamental scene breaks included in themes. Custom images possible.
- **Scrivener:** Custom separator text/characters configurable in Compile.
- **Reedsy:** Theme-dependent, minimal customization.

### Drop Caps
- **Vellum:** Yes — style-coordinated, with accent font option.
- **Atticus:** Yes — available in themes.
- **Scrivener:** Limited native support; requires manual formatting.
- **Reedsy:** Yes — theme-dependent.
- **D2D:** Yes — template-dependent.

### Font Embedding (EPUB)
- **Vellum:** Automatically embeds chosen fonts in EPUB. Body and heading fonts selectable independently.
- **Atticus:** Embeds fonts from its 1,500+ font library.
- **Scrivener:** Basic font embedding in EPUB output.
- **Reedsy:** Fonts embedded per theme.

### Headers, Footers & Running Headers
- **Vellum:** Header/footer carousel in Styles pane. Running headers with book title / chapter title on alternating pages. Not shown on chapter opening pages. Automatic page numbers.
- **Atticus:** Headers/footers with book title, chapter title, or author name. Page numbers in header or footer (author's choice). Roman numerals for front matter. Body starts on recto (right) page as page 1.
- **Scrivener:** Fully customizable headers/footers with placeholders.
- **Reedsy:** Basic header/footer support with page numbers.

### Table of Contents
- **Vellum:** Auto-generated. Vellum 4.0 supports choosing how many subheading levels to include.
- **Atticus:** Auto-generated, auto-updating as content changes.
- **Scrivener:** Can be auto-generated or manual. EPUB TOC included in templates.
- **Reedsy:** Auto-generated.

---

## 5. What Separates Great from Mediocre

### What Users Love (the "great" tier)

1. **One-click dual output** — Write once, export both EPUB and print-ready PDF from the same project (Vellum, Atticus). No need to maintain separate files.

2. **Live preview** — Seeing the formatted output as you work, not after a compile/export cycle (Vellum excels here).

3. **Automatic front/back matter** — Structured presets for title page, copyright, dedication, TOC, about author, also-by. No manual formatting needed (Atticus, Vellum, Reedsy).

4. **Professional output with zero expertise** — The tool handles typography rules (widows/orphans, proper em-dashes, smart quotes, correct page numbering, recto chapter starts) automatically.

5. **Reliable retailer compatibility** — EPUB validates on all stores (KDP, Apple Books, Kobo, Google Play). Print PDF accepted by KDP and IngramSpark without errors.

6. **Font embedding that works** — Fonts display correctly on all e-readers without bloating file size.

7. **Ornamental scene breaks** — Visual polish that makes books look professionally designed, not self-published.

### What Users Hate (the "mediocre" tier)

1. **Scrivener's Compile complexity** — The #1 complaint across all forums. Authors spend hours fighting Compile settings and often give up and export to DOCX, then format elsewhere.

2. **Too few templates** — Reedsy's 3 themes feel limiting. Authors want choice without needing to be designers.

3. **All books looking the same** — Vellum's limited fonts/styles mean experienced readers can spot a "Vellum book." More customization needed.

4. **No DOCX round-trip** — Authors need to send DOCX to editors, get edits back, and re-import. Vellum handles this; most others don't.

5. **Platform lock-in** — Vellum being Mac-only is its biggest complaint. Atticus won this segment by going cross-platform.

6. **Buggy EPUB output** — Google Docs produces broken EPUBs (all-italic text, missing TOC, wrong section breaks). Authors learn quickly to never use it for final output.

7. **No print-ready vs screen PDF distinction** — Reedsy is one of the few that offers both. Most tools only export one type, leaving authors confused about which to use where.

8. **Forced branding** — Reedsy's mandatory credit on the copyright page frustrates authors who want full control.

---

## 6. Summary: Table Stakes vs Differentiators

### Table Stakes (must have)
- EPUB 3 export (validates on all stores)
- Print-ready PDF at standard trim sizes
- DOCX export (for editor handoff)
- Auto-generated TOC
- Front matter: title page, copyright, dedication
- Back matter: about author, also-by
- Page numbers with proper front-matter Roman numeral handling
- Scene break formatting
- Chapter heading customization (at minimum: numbered, titled, or both)

### Differentiators (what wins users)
- Live formatting preview (not post-export)
- Multiple curated templates/themes (10+ minimum)
- Custom ornamental scene breaks
- Drop caps with style coordination
- Running headers (alternating book title / chapter title)
- Dual ebook+print from single source
- Font embedding with large font library
- Custom trim sizes beyond the standard 4-5
- Print-ready vs screen PDF distinction
- Full-bleed image support
- Large print edition support
- Mass market paperback sizes
- DOCX round-trip (export to editor, re-import edits)

### Premium Differentiators (where leaders are heading)
- Vellum 4.0's multi-level subheading TOC control
- Atticus's callout boxes and nonfiction layout elements
- Per-retailer back matter customization (different CTA for KDP vs Apple Books)
- AI-assisted formatting suggestions

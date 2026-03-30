# Book Interior Design Standards & Typography Research

Comprehensive research on modern book interior design, typography, trim sizes, and CSS/HTML export best practices.

---

## 1. Modern Book Interior Design Standards

### Body Text Typography

| Property | Standard Value | Notes |
|---|---|---|
| **Font family** | Serif (Garamond, Caslon, Baskerville, Minion Pro, Sabon) | Serif fonts guide the eye along lines; sans-serif only for headings/captions |
| **Font size** | 10pt-12pt (most common: 11pt) | 10pt for dense nonfiction, 12pt for large-print or children's |
| **Line height (leading)** | 120%-145% of font size | 11pt text = ~13-15pt leading; 12pt text = ~14-16pt leading |
| **Common leading pairs** | 10/12, 10/13, 11/13, 11/14, 12/15 | Expressed as "size/leading" in points |
| **Paragraph indent** | 0.2"-0.3" (1-1.5em) typical; up to 0.5" | First paragraph after heading: NO indent (flush left) |
| **Paragraph spacing** | 0pt (no extra space between paragraphs) | Fiction uses indents, NOT block paragraphs with spacing |
| **Text alignment** | Justified | With hyphenation enabled for even word spacing |
| **Characters per line** | 55-75 (ideal: 65-70) | Key readability metric; adjust margins/font to achieve this |
| **Max fonts per book** | 2-3 | One for body text, one for headings/display, optionally one for captions |

### Popular Book Fonts (with Free Alternatives)

| Commercial Font | Character | Free/Open Alternative | License |
|---|---|---|---|
| **Adobe Garamond Pro** | Classic, elegant, most widely used in publishing | **EB Garamond** | SIL OFL |
| **Minion Pro** | Versatile, clean, modern readability | **Crimson Pro** / **Crimson Text** | SIL OFL |
| **Baskerville** | Sharp, formal, literary | **Libre Baskerville** | SIL OFL |
| **Caslon** | Old-style, warm, reliable | **Libre Caslon Text** | SIL OFL |
| **Sabon** | Tschichold's Garamond; Penguin Classics standard | **Cormorant Garamond** | SIL OFL |
| **Palatino** | Warm, humanist, generous x-height | **Lora** | SIL OFL |
| **Bembo** | Renaissance, literary fiction | **Cardo** | SIL OFL |
| N/A (display) | Modern sans for headings | **Source Sans Pro**, **Inter** | SIL OFL |

### Chapter Opening Design

| Element | Convention | Specific Value |
|---|---|---|
| **Chapter sink** | White space from top of page to chapter title | ~1/3 page height (e.g., ~3" on a 9" page) |
| **Chapter number** | Centered or left-aligned, above title | Font size: 14-24pt display |
| **Chapter title** | Centered or left-aligned, below number | Font size: 18-30pt display |
| **Space after title** | Before first paragraph begins | 12-36pt (1-3 ems) |
| **First paragraph** | Flush left (NO indent) | — |
| **First line treatment** | Small caps for first few words/first line | Bridges drop cap to body text |
| **Drop cap** | Optional, 2-3 lines deep | See Section 3 for details |

### Running Headers & Footers

| Element | Verso (Left Page) | Recto (Right Page) |
|---|---|---|
| **Header content** | Book title OR author name | Chapter title |
| **Header alignment** | Left-aligned or centered | Right-aligned or centered |
| **Page number position** | Bottom-left or top-left (outer edge) | Bottom-right or top-right (outer edge) |
| **Font size** | 8-9pt | 8-9pt |
| **Font style** | Small caps or italic, same family as body | Small caps or italic, same family as body |
| **Suppressed on** | Chapter opening pages, blank pages, front matter title pages | Chapter opening pages, blank pages |

### Page Number Conventions

| Section | Number Style | Display |
|---|---|---|
| **Front matter** | Lowercase roman numerals (i, ii, iii, iv...) | Often suppressed/hidden |
| **Body text** | Arabic numerals (1, 2, 3...) starting at 1 | Visible in header or footer |
| **Back matter** | Arabic numerals (continues from body) | Visible |
| **Chapter opening pages** | Number present but often suppressed visually | Page still counted |
| **Blank pages** | Counted but not displayed | — |

---

## 2. Common Book Trim Sizes

### Fiction

| Size (W x H) | Name | Use Case |
|---|---|---|
| **4.25" x 6.87"** | Mass market paperback | Grocery store racks, romance, thriller, genre fiction |
| **5" x 8"** | Small trade | Literary fiction, novellas |
| **5.25" x 8"** | Digest | Memoir, literary fiction |
| **5.5" x 8.5"** | Digest / Trade | **Most popular for fiction**. Novels, literary fiction, memoir |
| **6" x 9"** | Trade | **Most versatile size overall**. Fiction, nonfiction, everything |

### Nonfiction

| Size (W x H) | Name | Use Case |
|---|---|---|
| **5.5" x 8.5"** | Digest | Self-help, memoir, business |
| **6" x 9"** | Trade | **Most common for nonfiction**. Business, history, biography |
| **7" x 10"** | Large trade | Textbooks, cookbooks, reference |
| **8.5" x 11"** | Letter | Workbooks, manuals, academic |

### Hardcover

| Size (W x H) | Use Case |
|---|---|
| **5.5" x 8.5"** | Literary fiction, memoir |
| **6" x 9"** | **Most common hardcover**. General fiction and nonfiction |
| **6.14" x 9.21"** | Royal (UK standard) |
| **7" x 10"** | Art books, illustrated nonfiction |

### Recommended Default Sizes for Templates

1. **5.5" x 8.5"** — Best for fiction and literary works
2. **6" x 9"** — Best all-purpose size; covers fiction and nonfiction
3. **5" x 8"** — Compact literary/novella option

---

## 3. Drop Caps in Modern Literature

### Usage Conventions

- **Where used**: Chapter openings ONLY (never at scene breaks or mid-chapter)
- **Frequency**: Common in literary fiction, historical fiction, and premium editions; less common in genre fiction, nonfiction, and digital-first books
- **When appropriate**: Books aiming for a "literary" or "classical" feel; NOT for every book
- **When to skip**: Minimalist designs, genre paperbacks, nonfiction, digital-primary formats

### Specifications

| Property | Standard | Notes |
|---|---|---|
| **Drop height** | 2-3 lines | 2 lines is conservative; 3 lines is dramatic |
| **Style** | Dropped (inset into text block) | Most common modern style |
| **Alternative styles** | Raised cap, hanging cap (in margin) | Less common; hanging caps are elegant but spacing-tricky |
| **Font** | Same as body OR decorative display font | Must harmonize with body text |
| **First-line bridge** | Small caps for first word/phrase/line | Creates visual transition from drop cap to body |
| **Quotation marks** | Often omitted or placed in margin at ~50% of drop cap size | A typographic convention when chapter opens with dialogue |

### Common Problems with Drop Caps

1. **Built-in side-bearing**: Auto drop caps create a gap between the initial and text edge
2. **Character shape variation**: Round letters (O, C, Q) and descender letters (Q, J) need per-character positioning
3. **Dialogue openings**: Quotation marks before the drop cap letter create awkward spacing
4. **Short first words**: "I" or "A" as drop cap looks odd—consider dropping the first 2-3 characters instead
5. **Digital rendering**: CSS `::first-letter` has inconsistent browser support for fine-tuning

### Recommendation for Templates

Offer drop caps as an **optional toggle**, defaulting to OFF for simplicity. When enabled:
- 2-line drop cap in the body font (or a specified display font)
- Small caps for the remainder of the first line
- First paragraph flush left (no indent)

---

## 4. Edge Cases That Break Book Templates

### 4.1 Orphans & Widows

| Term | Definition | CSS Property | Recommended Value |
|---|---|---|---|
| **Widow** | Last line of a paragraph stranded at the TOP of a new page | `widows` | `2` or `3` |
| **Orphan** | First line of a paragraph stranded at the BOTTOM of a page | `orphans` | `2` or `3` |
| **Runt** | Very short last line of a paragraph (1-2 words) | No CSS property | Manual control only |

**CSS:**
```css
p {
  orphans: 2;
  widows: 2;
}
```

Note: CSS can set minimums but cannot perfectly eliminate all cases. Professional typesetting often requires manual adjustment (tracking, soft hyphens, rewriting).

### 4.2 Chapter Titles — Very Long Titles

- Titles longer than 1-2 lines need overflow handling
- **Solution**: Cap display size, allow multi-line wrapping, reduce font size for long titles
- Disable hyphenation on titles (`hyphens: none`)
- Consider truncation or line-break hints for extreme cases
- Test with titles of 5-10 words AND 50+ character single words

### 4.3 Scene Breaks / Section Breaks (Dinkus)

| Style | Example | Use Case |
|---|---|---|
| **Three asterisks** | * * * | Most common, universal |
| **Three dots/bullets** | . . . or ● ● ● | Clean alternative |
| **Ornamental dingbat** | ❦ ❧ ✦ ✧ | Literary/decorative books |
| **Simple line** | ——— | Minimalist |
| **Extra white space** | (1-2 blank lines) | Modern/literary; risky if at page boundary |
| **Custom illustration** | Small graphic | Premium editions |

**Critical edge case**: A scene break that falls at the top or bottom of a page becomes invisible (reader doesn't know there was a break). Always use a visible marker, not just white space.

**CSS:**
```css
.scene-break {
  text-align: center;
  margin: 1.5em 0;
  page-break-inside: avoid;
  page-break-after: avoid; /* Don't strand break marker */
}
```

### 4.4 Front Matter

**Standard order (Chicago Manual of Style):**

1. **Half-title page** (title only, no subtitle/author) — recto
2. **Card page** (blank, or series/frontispiece/also-by) — verso
3. **Title page** (full title, subtitle, author, publisher) — recto
4. **Copyright page** (copyright, ISBN, credits, disclaimer) — verso
5. **Dedication** (optional) — recto
6. **Epigraph** (optional) — recto (or verso after dedication)
7. **Table of contents** — recto
8. **Foreword** — recto
9. **Preface** — recto
10. **Acknowledgments** (can also be back matter) — recto

**Rules:**
- Each major section starts on a **recto** (right-hand, odd) page
- This means blank verso pages are EXPECTED between sections
- Page numbering: lowercase roman numerals (i, ii, iii...)
- Page numbers are counted but often NOT displayed on front matter pages
- The half-title page is page i (even if the number isn't printed)

### 4.5 Back Matter

**Standard order:**
1. Acknowledgments (if not in front matter)
2. Appendix/Appendices
3. Notes/Endnotes
4. Glossary
5. Bibliography/References
6. Index
7. About the Author
8. Also By (if not in front matter)
9. Colophon (optional — describes typefaces and production details)

**Rules:**
- Page numbering continues in Arabic from body text
- Each section typically starts on a recto page (less strict than front matter)
- Running headers typically show the section name

### 4.6 Running Headers/Footers Edge Cases

- **Suppress on**: Chapter opening pages, part opener pages, blank pages, front matter display pages
- **Content changes**: Must update when crossing chapter boundaries (use CSS `string-set`)
- **Long chapter titles**: May need truncation or abbreviation in the running header
- **Books with no chapters**: Use book title on both sides, or suppress headers entirely

### 4.7 Page Numbering Edge Cases

- **Front matter → body transition**: Reset counter from roman to Arabic at chapter 1
- **Blank pages**: Counted but number not displayed
- **Chapter opening pages**: Counted; number usually at bottom-center or suppressed
- **Back matter**: Continues Arabic numbering from body (no reset)
- **Named pages in CSS**: Use `@page front-matter { ... }` and `@page body { ... }` with separate counter styles

### 4.8 Images/Illustrations in Text

- **Resolution**: 300 DPI minimum for print
- **Bleed**: Extend 0.125" (3.18mm) past trim edge on all sides
- **Safety margin**: Keep text 0.5" inside trim edge
- **Inline images**: Use `page-break-inside: avoid` on figure + caption
- **Full-page images**: Place on their own page; consider recto vs. verso placement
- **Captions**: Smaller font (8-9pt), often italic, numbered sequentially (Figure 1.1, 1.2...)

### 4.9 Block Quotes & Epigraphs

**Block Quotes:**
- Indented left (and sometimes right) by 0.25"-0.5"
- Same font as body text, OR slightly smaller (10pt if body is 11pt)
- No quotation marks (the indentation signals a quote)
- Single-spaced or same leading as body text
- Attribution: em dash + author, right-aligned or on new line

**Epigraphs:**
- Indented or centered, smaller font or italicized
- Often at chapter openings, below chapter title
- Attribution: em dash + author name + work title (no quotation marks)
- Consistent styling throughout the book

**CSS:**
```css
blockquote {
  margin: 1em 1.5em;
  font-size: 0.95em;
  page-break-inside: avoid;
}

.epigraph {
  margin: 1em 2em;
  font-style: italic;
  font-size: 0.9em;
  page-break-inside: avoid;
}

.epigraph .attribution {
  text-align: right;
  font-style: normal;
  margin-top: 0.5em;
}
```

### 4.10 Poetry & Verse

- **Alignment**: Left-aligned (NOT centered or justified)
- **Poem positioning**: Center the poem block on its longest line
- **Line wrapping**: Use hanging indent for overflow lines (indent continuation ~1-2em)
- **Stanza breaks**: One blank line between stanzas
- **Character count**: Aim for 45-75 characters per line
- **Page breaks**: Avoid breaking mid-stanza; use `page-break-inside: avoid` on stanza containers

**CSS:**
```css
.verse {
  margin: 1em 0;
  padding-left: 2em;
  text-indent: -2em; /* hanging indent for wrapped lines */
  text-align: left;
  hyphens: none;
  page-break-inside: avoid;
}

.stanza + .stanza {
  margin-top: 1em;
}
```

### 4.11 Lists (Numbered & Bulleted)

- **Indent**: Lists indented 0.25"-0.5" from body text margin
- **Spacing**: Tighter than body paragraphs; list items close together
- **Nested lists**: Additional 0.25" indent per level
- **Page breaks**: Avoid breaking between a list label and its first line
- **Numbering style**: Match book's overall aesthetic (period vs. parenthesis)

### 4.12 Footnotes & Endnotes

**Footnotes:**
- Font size: 8-9pt (smaller than body)
- Separator: Thin horizontal rule above footnote area
- Reference marks: Superscript numbers in body text
- Reset numbering: Per chapter (not per page)
- CSS: `float: footnote` (Prince XML); no native CSS standard yet

**Endnotes:**
- Font size: 9-10pt
- Placed: End of chapter or end of book
- Advantage: Cleaner page layout
- Disadvantage: Reader must flip back and forth

### 4.13 Special Characters & Unicode

- **Em dash** (—): No spaces around in American English; thin spaces in British
- **En dash** (–): Number ranges (pp. 100–120), compound adjectives
- **Curly/smart quotes**: " " ' ' — NEVER straight quotes in print
- **Ellipsis**: Use proper ellipsis character (…) or three periods with thin spaces
- **Ligatures**: fi, fl, ff, ffi, ffl — should be enabled in OpenType fonts
- **Non-breaking spaces**: Before em dashes, between number and unit
- **Font coverage**: Ensure chosen font covers all characters needed (accents, symbols)

### 4.14 Very Short Chapters (1-2 Pages)

- May leave excessive blank space if forced to recto-start
- Consider relaxing the "start on recto" rule for very short chapters
- Or: Allow chapters to start on either page when chapter count is high
- Test: A 1-paragraph chapter should still look intentional, not broken

### 4.15 Books with No Chapters

- Use section breaks (dinkus/ornamental) instead
- Running headers: Show book title on both pages, or suppress
- Page numbering: Straight through, no resets
- Consider: Part divisions or numbered sections as lightweight structure

### 4.16 Blank Pages (Recto/Verso Rules)

- **Intentionally blank**: When a section ends on a recto page and the next must start on a recto
- **Display**: Truly blank (most common) or with "This page intentionally left blank" (academic/legal)
- **Count**: Always counted in pagination; never displayed with a page number
- **CSS**: `@page :blank { @top-center { content: none } }`
- **Minimize**: To reduce blank pages, allow some sections to start on either page (trade-off between elegance and paper waste)

---

## 5. Curated "Basic Pack" — 3 Versatile Template Styles

Based on what major publishers use and what covers the widest range of modern literature:

### Template 1: "Classic" (Traditional Literary)

**Inspiration**: Penguin Classics, Knopf literary fiction, Vintage paperbacks

| Property | Value |
|---|---|
| **Body font** | EB Garamond (or Cormorant Garamond) at 11pt |
| **Heading font** | Same as body (EB Garamond) or small caps |
| **Leading** | 14pt (11/14) |
| **Trim size** | 5.5" x 8.5" |
| **Margins** | Top: 0.825", Bottom: 0.825", Outside: 0.625", Gutter: 0.75" |
| **Paragraph indent** | 0.25" (1.5em) |
| **Chapter opening** | 1/3 page sink, chapter number in small caps, title in italic |
| **Drop cap** | Optional, 2-line, body font |
| **First line** | Small caps for first 3-5 words |
| **Running headers** | Verso: book title (italic), Recto: chapter title (italic) |
| **Page numbers** | Outer bottom of footer, 9pt |
| **Scene breaks** | Three spaced asterisks (* * *) or fleuron (❧) |
| **Best for** | Literary fiction, historical fiction, memoir, classics |

### Template 2: "Modern" (Clean Contemporary)

**Inspiration**: FSG, Graywolf Press, contemporary literary fiction

| Property | Value |
|---|---|
| **Body font** | Crimson Pro at 11pt |
| **Heading font** | Source Sans Pro (or similar clean sans-serif) |
| **Leading** | 15pt (11/15) — slightly more open |
| **Trim size** | 5.5" x 8.5" (or 6" x 9") |
| **Margins** | Top: 0.875", Bottom: 0.825", Outside: 0.75", Gutter: 0.75" |
| **Paragraph indent** | 0.2" (1em) |
| **Chapter opening** | 1/3 page sink, chapter number in sans-serif, title in serif |
| **Drop cap** | None (flush-left first paragraph only) |
| **First line** | No special treatment; first paragraph flush left |
| **Running headers** | Verso: author name (small caps, sans), Recto: chapter title (small caps, sans) |
| **Page numbers** | Outer top of header, 8pt, same line as running head |
| **Scene breaks** | Single centered bullet (●) or extra white space with hairline rule |
| **Best for** | Contemporary fiction, creative nonfiction, essay collections |

### Template 3: "Professional" (Nonfiction/Trade)

**Inspiration**: Harper Nonfiction, W.W. Norton, university press books

| Property | Value |
|---|---|
| **Body font** | Libre Baskerville at 10.5pt |
| **Heading font** | Source Sans Pro or body font in bold/small caps |
| **Leading** | 14pt (10.5/14) |
| **Trim size** | 6" x 9" |
| **Margins** | Top: 1", Bottom: 0.875", Outside: 0.625", Gutter: 0.875" |
| **Paragraph indent** | 0.25" |
| **Chapter opening** | 1/3 page sink, chapter number + title, optional subtitle |
| **Drop cap** | None |
| **First line** | Small caps for first word or phrase |
| **Running headers** | Verso: book title (small caps), Recto: chapter title (small caps) |
| **Page numbers** | Bottom center, 9pt |
| **Scene breaks** | Three spaced asterisks or extra line space |
| **Footnotes/endnotes** | Supported; footnotes at 8pt with thin rule separator |
| **Best for** | Nonfiction, business, self-help, history, biography, academic |

---

## 6. CSS/HTML Book Typography Best Practices

### Page Setup

```css
@page {
  size: 5.5in 8.5in;          /* Trim size */
  margin: 0.825in 0.625in 0.825in 0.75in; /* Top Right Bottom Left(Gutter) */
}

@page :left {
  margin-left: 0.625in;       /* Outside margin on verso */
  margin-right: 0.75in;       /* Gutter on verso */
}

@page :right {
  margin-left: 0.75in;        /* Gutter on recto */
  margin-right: 0.625in;      /* Outside margin on recto */
}
```

### Running Headers & Page Numbers

```css
/* Store chapter title for running headers */
h1.chapter-title {
  string-set: chapter-title content();
}

/* Verso (left page): book title on left, page number on right */
@page :left {
  @top-left {
    content: "Book Title";
    font-size: 9pt;
    font-style: italic;
  }
  @bottom-left {
    content: counter(page);
    font-size: 9pt;
  }
}

/* Recto (right page): chapter title on right, page number on right */
@page :right {
  @top-right {
    content: string(chapter-title);
    font-size: 9pt;
    font-style: italic;
  }
  @bottom-right {
    content: counter(page);
    font-size: 9pt;
  }
}

/* Suppress headers on chapter opening pages */
@page chapter-start :first {
  @top-left { content: none; }
  @top-right { content: none; }
}

/* Blank pages */
@page :blank {
  @top-left { content: none; }
  @top-right { content: none; }
  @bottom-left { content: none; }
  @bottom-right { content: none; }
}
```

### Front Matter vs. Body Numbering

```css
/* Front matter: lowercase roman numerals */
.front-matter {
  page: front;
  counter-reset: page 1;
}

@page front {
  @bottom-center {
    content: counter(page, lower-roman);
    font-size: 9pt;
  }
}

/* Body: Arabic numerals, reset to 1 */
.body-matter {
  page: body;
  counter-reset: page 1;
}

@page body {
  @bottom-right {
    content: counter(page);
  }
}
```

### Typography Essentials

```css
body {
  font-family: "EB Garamond", "Garamond", serif;
  font-size: 11pt;
  line-height: 1.35;             /* ~14.85pt leading */
  text-align: justify;
  hyphens: auto;
  -webkit-hyphens: auto;
  orphans: 2;
  widows: 2;
  font-variant-ligatures: common-ligatures;
  font-feature-settings: "liga", "clig";
}

/* Headings: no hyphenation */
h1, h2, h3, h4, h5, h6 {
  hyphens: none;
  page-break-after: avoid;
  page-break-inside: avoid;
}

/* Keep heading with following paragraph */
h1 + p, h2 + p, h3 + p {
  page-break-before: avoid;
}
```

### Paragraph Formatting

```css
p {
  text-indent: 0.25in;
  margin: 0;
  padding: 0;
}

/* First paragraph after heading: no indent */
h1 + p,
h2 + p,
h3 + p,
.chapter-opening > p:first-child,
blockquote + p,
.scene-break + p {
  text-indent: 0;
}
```

### Chapter Openings

```css
.chapter {
  page: chapter-start;
  break-before: right;          /* Always start on recto */
  padding-top: 33%;             /* 1/3 page sink */
}

.chapter-number {
  font-variant: small-caps;
  letter-spacing: 0.1em;
  font-size: 12pt;
  text-align: center;
  margin-bottom: 0.5em;
}

.chapter-title {
  font-size: 20pt;
  text-align: center;
  margin-bottom: 2em;
  hyphens: none;
  line-height: 1.2;
}
```

### Drop Caps

```css
.chapter > p:first-of-type::first-letter {
  float: left;
  font-size: 3.2em;             /* ~2-3 lines */
  line-height: 0.8;
  padding-right: 0.05em;
  margin-top: 0.05em;
  font-weight: normal;
}

/* Small caps bridge for first line */
.chapter > p:first-of-type::first-line {
  font-variant: small-caps;
  letter-spacing: 0.05em;
}
```

### Scene Breaks

```css
.scene-break {
  text-align: center;
  margin: 1.5em 0;
  page-break-inside: avoid;
  page-break-before: avoid;     /* Don't put break at top of page alone */
  page-break-after: avoid;      /* Keep with following text */
}

.scene-break::before {
  content: "* * *";
  letter-spacing: 0.5em;
}
```

### Block Quotes & Epigraphs

```css
blockquote {
  margin: 1em 1.5em 1em 1.5em;
  font-size: 0.95em;
  page-break-inside: avoid;
}

.epigraph {
  margin: 0.5em 2em 2em 2em;
  font-style: italic;
  font-size: 0.9em;
  page-break-inside: avoid;
}

.epigraph .attribution {
  text-align: right;
  font-style: normal;
  margin-top: 0.3em;
}

.epigraph .attribution::before {
  content: "— ";
}
```

### Footnotes (Prince XML)

```css
.footnote {
  float: footnote;
  font-size: 8pt;
  line-height: 1.3;
}

.footnote::footnote-call {
  content: counter(footnote);
  font-size: 0.7em;
  vertical-align: super;
  line-height: none;
}

.footnote::footnote-marker {
  content: counter(footnote) ". ";
  font-size: 8pt;
}

@page {
  @footnote {
    border-top: 0.5pt solid #333;
    padding-top: 6pt;
    margin-top: 12pt;
  }
}
```

### Table of Contents

```css
.toc a {
  text-decoration: none;
  color: inherit;
}

.toc a::after {
  content: leader(".") target-counter(attr(href), page);
  font-variant-numeric: tabular-nums;
}

.toc li {
  list-style: none;
  margin: 0.3em 0;
}
```

### Images & Figures

```css
figure {
  page-break-inside: avoid;
  margin: 1.5em 0;
  text-align: center;
}

figure img {
  max-width: 100%;
  height: auto;
}

figcaption {
  font-size: 9pt;
  font-style: italic;
  margin-top: 0.5em;
  text-align: center;
}
```

### Poetry / Verse

```css
.poem {
  margin: 1.5em auto;
  max-width: 80%;
  page-break-inside: avoid;
}

.verse-line {
  text-indent: -1.5em;
  padding-left: 1.5em;          /* Hanging indent for wrapped lines */
  text-align: left;
  hyphens: none;
}

.stanza {
  margin-bottom: 1em;
  page-break-inside: avoid;
}
```

### Key CSS Properties Reference

| Property | Purpose | Recommended Value |
|---|---|---|
| `size` | Page dimensions | `5.5in 8.5in` or `6in 9in` |
| `margin` (in @page) | Page margins | See trim size tables above |
| `break-before` | Force page break | `right` (recto), `left` (verso), `page`, `avoid` |
| `break-after` | Break after element | `avoid` for headings |
| `break-inside` | Prevent mid-element break | `avoid` for figures, quotes, stanzas |
| `orphans` | Min lines at page bottom | `2` |
| `widows` | Min lines at page top | `2` |
| `hyphens` | Auto-hyphenation | `auto` for body, `none` for headings |
| `text-align` | Justification | `justify` for body |
| `string-set` | Store text for running headers | `string-set: title content()` |
| `counter()` | Page numbers | `counter(page)` |
| `target-counter()` | Cross-reference page numbers | `target-counter(attr(href), page)` |
| `leader()` | TOC dot leaders | `leader(".")` |
| `float: footnote` | Footnote placement (Prince) | — |
| `font-variant-ligatures` | Enable ligatures | `common-ligatures` |
| `font-variant: small-caps` | Small caps | For running headers, first lines |

### PDF Engine Comparison

| Feature | Prince XML | WeasyPrint | Paged.js |
|---|---|---|---|
| **Cost** | Commercial ($$$) | Free/Open Source | Free/Open Source |
| **Footnotes** | Full support | No support | Limited |
| **Running headers** | Full support | Partial | Partial |
| **Leader dots (TOC)** | Yes | Limited | Limited |
| **Orphan/widow control** | Excellent | Good | Browser-dependent |
| **Hyphenation** | Built-in | Built-in | Browser-dependent |
| **Named strings** | Yes | Partial | Polyfilled |
| **Cross-references** | Yes | No | Limited |
| **Quality** | Production-grade | Good for simple books | Variable |
| **Recommendation** | Best for complex books | Good for simple PDF export | Avoid (unmaintained) |

---

## Margin Reference Table (Complete)

### Recommended Margins by Trim Size

| Trim Size | Gutter (Inside) | Outside | Top | Bottom |
|---|---|---|---|---|
| **5" x 8"** | 0.75" | 0.5" | 0.75" | 0.75" |
| **5.25" x 8"** | 0.75" | 0.5"-0.625" | 0.75" | 0.75" |
| **5.5" x 8.5"** | 0.75" | 0.625" | 0.825" | 0.825" |
| **6" x 9"** | 0.875" | 0.625"-0.75" | 0.875"-1" | 0.875" |
| **7" x 10"** | 1" | 0.75" | 1" | 0.875" |
| **8.5" x 11"** | 1.25" | 0.875" | 1.25" | 0.875" |

### KDP Minimum Gutter by Page Count

| Page Count | Minimum Gutter |
|---|---|
| 24-150 pages | 0.375" |
| 151-300 pages | 0.5" |
| 301-500 pages | 0.625" |
| 501-700 pages | 0.75" |
| 701-828 pages | 0.875" |

### KDP Minimum Outside/Top/Bottom Margins

| Type | Minimum |
|---|---|
| Without bleed | 0.25" |
| With bleed | 0.375" |

---

## Sources

- [Illumination Graphics — Complete Guide to Book Interior Design](https://illuminationgraphics.com/complete-guide-book-interior-design-layout-fonts-formatting/)
- [Reedsy — Standard Book Sizes in Publishing](https://reedsy.com/studio/resources/standard-book-sizes/)
- [Speakipedia — Book Design Basics: Drop Caps](https://speakipedia.com/book-design-part-6/)
- [Speakipedia — Book Design Basics: Small Capitals](https://speakipedia.com/book-design-part-5/)
- [Wikipedia — Dinkus](https://en.wikipedia.org/wiki/Dinkus)
- [Wikipedia — Widows and Orphans](https://en.wikipedia.org/wiki/Widows_and_orphans)
- [Wikipedia — Page Numbering](https://en.wikipedia.org/wiki/Page_numbering)
- [Wikibooks — Basic Book Design: Margins](https://en.wikibooks.org/wiki/Basic_Book_Design/Margins)
- [Wikibooks — Basic Book Design: Headers, Footers, and Page Numbers](https://en.wikibooks.org/wiki/Basic_Book_Design/Headers,_Footers,_and_Page_Numbers)
- [KDP — Set Trim Size, Bleed, and Margins](https://kdp.amazon.com/en_US/help/topic/GVBQ3CMEQW3W2VL6)
- [Arash Jahani — Trim Size & Margins](https://arashjahani.com/2021/09/12/trim-size-margins/)
- [Smashing Magazine — Designing For Print With CSS](https://www.smashingmagazine.com/2015/01/designing-for-print-with-css/)
- [Prince XML — Running Headers Guide](https://css4.pub/2024/running-headers/)
- [Electric Book Works — Book Production with CSS Paged Media](https://electricbookworks.com/thinking/book-production-with-css-paged-media/)
- [Penguin Random House — Behind the Scenes: Designing Your Book's Interior](https://authornews.penguinrandomhouse.com/behind-the-scenes-designing-your-books-interior/)
- [Wikipedia — Penguin Composition Rules](https://en.wikipedia.org/wiki/Penguin_Composition_Rules)
- [Barker Books — 7 Enduring Popular Book Fonts](https://barkerbooks.com/popular-book-fonts/)
- [Reedsy — 10 Brilliant Fonts for Your Book Layout](https://reedsy.com/blog/guide/book-design/book-fonts/)
- [Book Design Made Simple — Running Heads](https://www.bookdesignmadesimple.com/designing-book-running-heads/)
- [Dear Editor — What's the Rule for Indenting First Paragraphs?](https://www.deareditor.com/2013/07/re-whats-the-rule-for-indenting-first-paragraphs/)
- [CMOS Shop Talk — Chicago-Style Epigraphs and Sources](https://cmosshoptalk.com/2018/07/20/chicago-style-epigraphs-and-sources/)
- [Foglio Print — Footnotes vs. Endnotes](https://www.foglioprint.com/blog/footnotes-vs-endnotes-how-and-where-to-use-them-in-your-book)
- [PrintCSS — Page Selectors and Page Breaks](https://printcss.net/articles/page-selectors-and-page-breaks)
- [DocRaptor — CSS Paged Media Module](https://docraptor.com/css-paged-media)
- [DIY Book Formats — Poetry Book Formatting](https://diybookformats.com/poetrybook/)
- [Electric Book Template — Poetry](https://electricbookworks.github.io/electric-book/docs/editing/poetry.html)

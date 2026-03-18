# Standard Book Interior Template: Publishing Industry Reference

Research compiled from current publishing industry standards, KDP guidelines, Chicago Manual of Style conventions, and common defaults from tools like Vellum and Atticus.

---

## 1. The Standard "Classic" Book Interior

The most common, safe, professional book layout for traditionally published fiction follows these principles:

- **Serif body font** at 10.5--12pt on a 6x9 trade paperback
- **First-line paragraph indents** (not block paragraphs)
- **Justified text alignment** with careful hyphenation
- **Ragged-right (left-aligned)** is sometimes used but justified is the strong default
- **Chapter openings** drop about 1/3 down the page ("sink")
- **Running headers** with author name and book/chapter title
- **Page numbers** in headers or footers, suppressed on chapter-opening pages
- **Generous white space** -- the interior should feel airy, not cramped

---

## 2. Typography Specifics

### Body Fonts (Serif Families Used in Published Books)

The following are the most commonly used body text fonts in traditionally published books:

| Font | Character | Common Use |
|------|-----------|------------|
| **Garamond** (Adobe Garamond Pro, EB Garamond) | Elegant, warm, slightly old-style | Literary fiction, general fiction, memoirs -- the single most common choice |
| **Caslon** (Adobe Caslon Pro) | Sturdy, versatile, classic | Fiction and nonfiction -- "When in doubt, use Caslon" |
| **Baskerville** | Sharper serifs, transitional | Academic, literary fiction, nonfiction |
| **Palatino** (Book Antiqua) | Humanist, slightly calligraphic | Fiction, nonfiction, textbooks |
| **Minion Pro** | Modern Adobe workhorse | Widely used in contemporary publishing |
| **Sabon** | Based on Garamond, very clean | High-end literary publishing |
| **Bembo** | Renaissance humanist | Penguin Classics, literary fiction |
| **Janson** | Dutch old-style | Fiction, poetry |
| **Goudy Old Style** | Warm, slightly eccentric | General trade books |
| **Times New Roman** | Ubiquitous but considered generic | Rarely used in professional book interiors despite manuscript use |

**Key insight:** Garamond variants are the single most common body font family in traditionally published books.

### Font Sizes

| Context | Size |
|---------|------|
| **6x9 trade paperback body text** | 11--12pt (11pt is the most common default) |
| **5x8 / 5.25x8 mass market** | 10--10.5pt |
| **5.5x8.5 trade** | 10.5--11pt |
| **Large print editions** | 14--16pt |
| **Ebook body text** | Not specified in absolute units; use `1em` or leave as device default |

### Line Spacing / Leading

- **Standard ratio:** 120--145% of font size
- **For 11pt body text:** 13.2--15.5pt leading (most common: ~14pt, i.e. ~127%)
- **Comfortable fiction reading:** around 135% (11pt text / ~14.8pt leading)
- **Ebooks:** 1.2--1.5em line-height (CSS); reader can override

### Paragraph Formatting

- **Indent paragraphs, not block paragraphs.** Block paragraphs (extra space between, no indent) are for business documents, web, and some nonfiction. Fiction and most trade nonfiction use first-line indents with no extra space between paragraphs.
- **First paragraph of a chapter: flush left (no indent).** This is the strong convention per Chicago Manual of Style and virtually all major publishers. The indent is unnecessary after a heading because the paragraph start is already visually obvious.
- **First paragraph after a scene break: flush left (no indent).** Same reasoning -- the break already signals a new paragraph.
- Some designers also apply **small caps** or **all caps** to the first line or first few words of a chapter opening paragraph as an additional visual cue.

### Standard Paragraph Indent Size

- **Print:** 0.2--0.3 inches (1.5--2em) is the professional standard for trade books. **0.25 inches is the most common value.**
  - Note: 0.5 inches is a manuscript/Word default and is too large for a typeset book.
- **Ebook:** `1.5em` or `2em` in CSS (typically translates to a similar visual indent)

---

## 3. Chapter Openings

### Vertical Space Before Chapter Title ("Sink")

- The chapter title begins approximately **1/3 of the way down the page** (roughly 3 inches from the top on a 9-inch page).
- This "sink" creates a strong visual break and signals a new chapter.
- The exact position: **2.5--3.5 inches from the top of the page** (including the top margin).
- Some designers use a fixed sink (e.g., exactly 3 inches); others position it relative to the text block.

### Chapter Title Styling

- **Chapter number:** Can be spelled out ("Chapter Three"), numeric ("Chapter 3" or just "3"), or Roman numeral ("III"). Spelled-out or plain numerals are most common in fiction.
- **Font:** Often the same serif as the body, or a contrasting display/sans-serif font for visual interest.
- **Size:** 14--20pt for the chapter title (16--18pt is the sweet spot).
- **Weight/style:** Commonly **small caps**, **all caps**, or **bold**. All caps at a moderate size (14--16pt) is very common. Small caps is considered more elegant.
- **Alignment:** Almost always **centered**.
- **Chapter subtitle** (if present): Smaller than the title, often italic, centered below.

### Drop Caps

- **Very common** in traditionally published fiction -- used in a large majority of novels from major publishers.
- Typically a **2--3 line drop cap** (the initial letter drops to the depth of 2--3 lines of body text).
- Applied to the **first paragraph of each chapter** only.
- The first word or first few words after the drop cap are often set in **small caps** as a lead-in.
- **Not used** when a chapter opens with dialogue (a quotation mark as a drop cap looks awkward -- some designers handle this by making the first letter after the opening quote the drop cap).
- **Ebooks:** Drop caps are problematic in reflowable EPUBs and are often simplified or omitted. Many ebook formatters substitute a **large initial letter** (raised cap) or skip them entirely.

### Space Between Chapter Title and First Paragraph

- Typically **2--4 blank lines** worth of space (roughly 0.5--1 inch or 36--72pt).
- The exact spacing depends on the overall design, but there should be clear breathing room.

---

## 4. Scene Breaks

### Standard Scene Break Markers

The scene break (also called a "section break") indicates a shift in time, place, or viewpoint within a chapter.

| Marker | Description | How Common |
|--------|-------------|------------|
| **Extra blank line** (white space only) | A single blank line between paragraphs, wider than normal paragraph spacing | Very common in traditionally published fiction. Simple and clean. |
| **Three asterisks** `* * *` (dinkus) | Three spaced asterisks centered on a line | The classic standard. Extremely common. Used in both manuscripts and published books. |
| **Three centered dots** `...` or `* * *` | Variation of the dinkus | Common |
| **Ornamental glyph / fleuron** | A decorative symbol (leaf, diamond, star, flourish) centered on the line | Common in higher-end trade fiction. Adds visual character. |
| **Short horizontal rule** | A centered line, typically 1--2 inches wide | Less common in fiction but used in some designs |
| **Blank line + flush-left first paragraph** | White space only; the first paragraph after the break is not indented | Very common -- the flush-left paragraph signals the break even without a visible marker |

**Most standard approach:** A **dinkus** (three spaced asterisks or dots) or a **small ornamental glyph** centered on a line, with extra space above and below. The first paragraph after the break is typically **flush left** (no indent).

**Important:** A blank-line-only scene break is risky if it falls at a page break -- the reader won't see it. This is why a visible marker (dinkus or ornament) is recommended for print. For ebooks, the same principle applies.

---

## 5. Page Layout (Print -- 6x9 Trade Paperback)

### Standard Margins

| Margin | Recommended | KDP Minimum |
|--------|------------|-------------|
| **Top** | 0.5--0.75" | 0.25" (no bleed) |
| **Bottom** | 0.625--0.75" | 0.25" (no bleed) |
| **Outside (thumb/fore-edge)** | 0.5--0.75" | 0.25" (no bleed) |
| **Inside (gutter)** | 0.75--0.875" (depends on page count) | 0.375" (<150pp), 0.5" (151-300pp), 0.625" (301-500pp), 0.75" (501-700pp), 0.875" (700+pp) |

**Professional recommendation for a 6x9 novel (200--350 pages):**
- Top: 0.5"
- Bottom: 0.75"
- Outside: 0.625--0.75"
- Inside (gutter): 0.75--0.875"

**Note:** KDP minimums are the absolute floor. Professional book interiors use larger margins than the KDP minimums. The text block should feel comfortably framed by white space, not pushed to the edges.

### Running Headers

| Left page (verso) | Right page (recto) |
|---|---|
| **Author name** | **Book title** |

This is the most common convention for **fiction**. Alternatives:
- Left: Book title / Right: Chapter title (common for **nonfiction**)
- Left: Author name / Right: Chapter title
- No running headers at all (some contemporary designs)

**Important rules:**
- **No running headers on chapter-opening pages.** Only a drop folio (page number at the bottom).
- **No running headers on blank pages** (verso blanks before recto chapter starts).
- Running headers are typically set in **small caps**, **italic**, or a **smaller font size** (8--9pt) than the body text.
- Separated from the text block by a small space or a thin rule.

### Page Numbers (Folios)

- **Regular pages:** Page numbers appear in the **header**, aligned to the **outside margin** (left on verso, right on recto). Alternatively, centered in the footer.
- **Chapter-opening pages:** Page number appears at the **bottom center** of the page (drop folio). No running header.
- **Front matter:** Numbered in **lowercase Roman numerals** (i, ii, iii, iv...).
- **Body text:** Numbered in **Arabic numerals** starting from 1 on the first page of chapter 1 (or on the half-title page, depending on convention).
- **Font:** Same as running header font, typically 8--9pt.

### Recto (Right-Page) Chapter Starts

- **Traditionally published fiction:** Recto chapter starts are the **standard convention**, especially for hardcovers and higher-quality trade paperbacks. This means inserting a blank verso page before a chapter that would otherwise start on a left page.
- **Nonfiction:** Almost always recto starts.
- **Self-published / cost-conscious fiction:** Many authors allow chapters to start on either page to reduce page count and printing costs. This is acceptable but less polished.
- **Ebooks:** Not applicable -- there are no left/right pages in a reflowable ebook.

**Verdict:** Recto chapter starts are the professional standard but not an absolute requirement for fiction paperbacks. For a "standard classic" look, use recto starts.

---

## 6. Front Matter Order

The standard sequence per Chicago Manual of Style and major publisher convention:

### Fiction (Novel)

1. **Half-title page** -- Just the book title, no subtitle or author name. Always recto.
2. **Also By page** (optional) -- "Also by [Author Name]" with a list of other titles. Verso (back of half-title). Can also go in back matter.
3. **Title page** -- Full title, subtitle (if any), author name, publisher logo/name. Always recto.
4. **Copyright page** -- Copyright notice, ISBN, edition info, printer info, permissions, "This is a work of fiction" disclaimer. Always verso (back of title page).
5. **Dedication** (optional) -- Short personal dedication. Recto. Keep it brief.
6. **Epigraph** (optional) -- A quotation that sets the tone for the book. Can share the dedication page or be on its own page.
7. **Table of Contents** (optional for fiction) -- Most novels omit this. Nonfiction always includes it. If present, starts recto.
8. **Author's Note / Foreword / Preface** (rare in fiction) -- If present, starts recto.
9. **Prologue** (if any) -- Starts recto. Part of the narrative.
10. **Part One title page** (if the book has parts) -- Recto.
11. **Chapter 1** -- Starts recto.

### Nonfiction

1. Half-title page (recto)
2. Series title or blank (verso)
3. Title page (recto)
4. Copyright page (verso)
5. Dedication (recto, optional)
6. Table of Contents (recto)
7. List of Figures/Tables (recto, if needed)
8. Foreword (recto)
9. Preface (recto)
10. Acknowledgments (recto -- can also go in back matter)
11. Introduction (recto)
12. Chapter 1

**Key rules:**
- Half-title, title page, and chapter 1 are **always recto** (right-hand pages).
- Copyright page is **always verso** (back of title page).
- Front matter pages are numbered in **lowercase Roman numerals** but the numbers are typically not displayed.

---

## 7. Back Matter

### Fiction -- Standard Sequence

1. **Epilogue** (if any -- this is really the end of the narrative)
2. **Author's Note** (optional -- historical context, research notes, etc.)
3. **Acknowledgments** -- Very common. Often the only back matter in a novel beyond "About the Author."
4. **Reading Group Guide / Discussion Questions** (optional)
5. **Glossary** (optional -- for fantasy/sci-fi with invented terms)
6. **About the Author** -- Short biography, 1--2 paragraphs. Very common.
7. **Also By [Author Name]** -- List of other books. Can also appear in front matter. Very common for self-published authors (cross-promotion).
8. **Preview / Excerpt of Next Book** (optional -- common in series)
9. **Colophon** (rare -- notes on the typeface and production)

### Nonfiction -- Standard Sequence

1. Epilogue / Afterword
2. Acknowledgments
3. Appendices
4. Endnotes (if not footnotes)
5. Glossary
6. Bibliography / Works Cited / References
7. List of Contributors
8. Index (always last of the substantive back matter)
9. About the Author
10. Colophon

**Self-publishing note:** For self-published fiction, the most critical back matter for discoverability and sales is: (1) Also By / Other Books, (2) About the Author with links, (3) a call to action for reviews or mailing list signup. Many indie authors place these immediately after the last chapter, before acknowledgments.

---

## 8. EPUB / Ebook Specifics

### How the Standard Ebook Interior Differs from Print

| Aspect | Print | Ebook (Reflowable EPUB/KF8) |
|--------|-------|-----------------------------|
| **Font** | Fixed, chosen by designer | Reader can override; embedded fonts optional |
| **Font size** | Fixed (e.g., 11pt) | Reader-adjustable; set body to `1em` (default) |
| **Line spacing** | Fixed leading (e.g., 14pt) | `line-height: 1.2` to `1.5` in CSS; reader can override |
| **Margins** | Fixed physical margins | Device/app controls margins; CSS left/right margins should be `0` for body text |
| **Paragraph indent** | 0.25" fixed | `text-indent: 1.5em` in CSS |
| **Justification** | Justified (typically) | Depends on device/reader preference |
| **Drop caps** | Common (2-3 line) | Problematic; often replaced with raised initial or omitted |
| **Running headers** | Author/title in header | Not used; device provides its own progress/location UI |
| **Page numbers** | Physical page numbers | Not applicable; devices use location/percentage |
| **Recto starts** | Blank pages inserted | Not applicable; no left/right page concept |
| **Chapter title sink** | 1/3 page drop | `margin-top: 3em` to `5em` on chapter heading (approximate) |
| **Scene breaks** | Dinkus or ornament | Same markers work; ensure visibility |
| **Images/ornaments** | Fixed placement | Must be responsive; use `max-width: 100%` |
| **Front matter** | Half-title, title page, copyright | Often simplified; half-title sometimes omitted. Copyright often placed at the end. |

### Font Embedding

- **Reflowable EPUBs:** You can embed fonts, but readers on most devices can override them. On Kindle devices, Amazon's Bookerly is the default reading font.
- **Full font files required:** Unlike PDF, you cannot embed a font subset in EPUB -- the entire font file must be included.
- **Licensing:** Many commercial fonts prohibit embedding in EPUBs. Open-source fonts (EB Garamond, Crimson Text, Lora, Merriweather) are commonly embedded.
- **Common embedded fonts for ebooks:** EB Garamond, Crimson Pro, Lora, Merriweather, Libre Baskerville, Source Serif Pro.
- **Practical advice:** Do not rely on embedded fonts for critical formatting. Design should work well with any font the reader chooses. Amazon KDP recommends leaving body text at default styling so the reader's device handles it.

### EPUB CSS Best Practices

- **Body text font-size:** `1em` or omit entirely (let device default)
- **Headings:** Size relative to body (`1.3em` to `2em`)
- **Paragraph indent:** `text-indent: 1.5em`; first paragraph of chapter: `text-indent: 0`
- **Margins:** Use `%` for left/right, `em` for top/bottom
- **Avoid:** Absolute units (`pt`, `px`, `in`) for text sizing
- **Line-height:** `1.3` to `1.5` (unitless values preferred)
- **KDP requirement:** Body text in Kindle books must use all defaults -- no custom font-size, line-height, or font-family on `<p>` body elements

---

## 9. Quick Reference: "Safe Default" Template

If you want to produce a single, standard, professional-looking book interior, here are the safe defaults:

| Setting | Value |
|---------|-------|
| **Trim size** | 6 x 9 inches |
| **Body font** | Garamond (or EB Garamond), 11pt |
| **Leading** | ~14pt (127% of font size) |
| **Paragraph indent** | 0.25 inches |
| **First paragraph** | Flush left (no indent) |
| **Text alignment** | Justified |
| **Margins (top/bottom/outside)** | 0.5" / 0.75" / 0.625" |
| **Gutter (inside margin)** | 0.75" (for 200-300 page book) |
| **Chapter sink** | ~3 inches from top of page |
| **Chapter title** | Small caps or all caps, centered, 16-18pt |
| **Drop cap** | 3-line drop cap, first word in small caps |
| **Scene break** | Centered dinkus (three spaced asterisks) or small ornament |
| **Running header (left)** | Author name, small caps, 8-9pt |
| **Running header (right)** | Book title, small caps, 8-9pt |
| **Page numbers** | Outside margin in header; bottom center on chapter openings |
| **Chapter starts** | Recto (right page) |
| **Front matter** | Half-title, title page, copyright, dedication |
| **Back matter** | Acknowledgments, About the Author, Also By |

---

## Sources

- [The Book Designer -- Chapter Openers](https://www.thebookdesigner.com/book-design-chapter-openers-part-openers/)
- [The Book Designer -- Book Layouts and Page Margins](https://www.thebookdesigner.com/book-layouts-page-margins/)
- [The Book Designer -- Front Matter](https://www.thebookdesigner.com/front-matter-book/)
- [The Book Designer -- Book Font Guide](https://www.thebookdesigner.com/book-font-guide/)
- [Book Design Made Simple -- Running Heads](https://www.bookdesignmadesimple.com/book/book-running-heads/)
- [Book Design Made Simple -- Front Matter Elements](https://www.bookdesignmadesimple.com/book-front-matter-elements/)
- [KDP -- Set Trim Size, Bleed, and Margins](https://kdp.amazon.com/en_US/help/topic/GVBQ3CMEQW3W2VL6)
- [KDP -- Text Guidelines (Reflowable)](https://kdp.amazon.com/en_US/help/topic/GH4DRT75GWWAGBTU)
- [CMOS Shop Talk -- Space Breaks in Fiction](https://cmosshoptalk.com/2019/11/12/space-breaks-in-fiction/)
- [CMOS FAQ -- First Paragraph Indent Convention](https://www.chicagomanualofstyle.org/qanda/data/faq/topics/ManuscriptPreparation/faq0223.html)
- [Chicago Manual of Style -- Back Matter Order](https://www.chicagomanualofstyle.org/qanda/data/faq/topics/NoneoftheAbove/faq0040.html)
- [Wikibooks -- Basic Book Design](https://en.wikibooks.org/wiki/Basic_Book_Design/Font)
- [Vellum Help -- Print Settings](https://help.vellum.pub/print/settings/)
- [Atticus -- How to Format Your Book](https://www.atticus.io/how-to-format-your-book-with-atticus/)
- [Atmosphere Press -- Book Formatting Industry Standards](https://atmospherepress.com/book-formatting-industry-standards/)
- [Illumination Graphics -- Complete Guide to Book Interior Design](https://illuminationgraphics.com/complete-guide-book-interior-design-layout-fonts-formatting/)
- [InDesign Skills -- Book Font Size](https://www.indesignskills.com/tutorials/book-font-size/)
- [PaperTrue -- Best Fonts for Books](https://www.papertrue.com/blog/best-fonts-for-books/)
- [Reedsy -- 10 Brilliant Fonts for Book Layout](https://reedsy.com/blog/guide/book-design/book-fonts/)
- [EditionGuard -- Best Fonts for eBooks 2025](https://www.editionguard.com/learn/best-fonts-e-books/)
- [EPUB 3 Best Practices -- Font Embedding (O'Reilly)](https://www.oreilly.com/library/view/epub-3-best/9781449329129/ch04.html)
- [DearEditor -- Indenting First Paragraphs](https://www.deareditor.com/2013/07/re-whats-the-rule-for-indenting-first-paragraphs/)
- [Speakipedia -- Drop Caps and Initial Impressions](https://speakipedia.com/book-design-part-6/)
- [DiggyPod -- Back Matter](https://www.diggypod.com/blog/book-back-matter/)
- [Lulu -- Front Matter and Back Matter](https://blog.lulu.com/bookend-your-book-front-matter-and-back-matter/)

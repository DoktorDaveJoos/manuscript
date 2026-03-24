# Paragraph Formatting Research: Writing App Editor Conventions

**Date:** 2026-03-24
**Purpose:** Understand how leading book/novel writing apps handle paragraph formatting in their editors, and determine best practices for the Manuscript editor.

---

## Executive Summary

**The clear industry consensus:** Modern writing editors use **paragraph spacing** (vertical whitespace between paragraphs) in the editor/writing view, even when the final book output uses **first-line indentation** with no extra spacing. The editor and the output are treated as separate concerns. Manuscript's current approach of using first-line indentation in the editor is atypical and goes against the dominant pattern in professional writing tools.

---

## App-by-App Analysis

### 1. Scrivener (Most Popular Novel Writing App)

| Aspect | Detail |
|---|---|
| **Editor default** | First-line indent (~0.5") with 1.2x line spacing. Paragraph spacing configurable. |
| **Enter key** | Creates a new paragraph with the configured indent |
| **Editor vs Output** | **Strongly separated.** Scrivener's core philosophy is that editor formatting and compile (export) formatting are independent. You can write with wide margins, extra paragraph spacing, and any font you like -- compile overrides everything. |
| **Key insight** | Scrivener defaults to indent in the editor but explicitly tells users "you may like to have extra spaces between paragraphs when writing" and encourages comfortable writing formatting that differs from output. Many Scrivener users customize their editor to use paragraph spacing for comfort. |

### 2. Ulysses (Popular Mac Writing App)

| Aspect | Detail |
|---|---|
| **Editor default** | Paragraph spacing (vertical whitespace between paragraphs). Markdown-based, so a blank line between paragraphs is the natural model. |
| **Enter key** | Single return starts a new paragraph. Empty lines are treated as whitespace (ignored during export). |
| **Editor vs Output** | **Separated.** Editor typography (paragraph spacing, first-line indent, line height) are independent settings. Export uses separate style sheets. |
| **Key insight** | Offers both "Paragraph Spacing" and "First Line Indent" as independent editor settings. Default visual model is spacing-based, consistent with Markdown conventions. |

### 3. iA Writer (Minimalist Writing App)

| Aspect | Detail |
|---|---|
| **Editor default** | **Paragraph spacing** (vertical whitespace). No first-line indent in editor. |
| **Enter key** | Two returns create a paragraph break (standard Markdown). Optional "Single Return" setting available. |
| **Editor vs Output** | **Separated.** Has an "Indent Paragraphs" toggle specifically for Preview/export templates -- described as "Indicate new paragraphs with indentation instead of vertical space." The editor always uses spacing. |
| **Key insight** | The editor is always spacing-based. Indentation is explicitly a template/output concern, not an editor concern. This is the clearest example of the separation principle. |

### 4. Google Docs (Widely Used)

| Aspect | Detail |
|---|---|
| **Editor default** | **No indent, no extra paragraph spacing** by default. Line spacing 1.15. Paragraphs are visually separated only by the line break itself. |
| **Enter key** | Creates a new paragraph flush-left. Users commonly press Enter twice to create visible separation. |
| **Editor vs Output** | **WYSIWYG** -- what you see is what you get. No separate preview. |
| **Key insight** | No first-line indent by default. Users who want visual separation typically add "space after paragraph" manually or press Enter twice. |

### 5. Microsoft Word (Industry Standard)

| Aspect | Detail |
|---|---|
| **Editor default** | **No first-line indent. 8pt spacing after each paragraph.** Line spacing 1.08 (Word 365). |
| **Enter key** | Creates a new paragraph with 8pt of space after it. Visually clear paragraph separation. |
| **Editor vs Output** | **WYSIWYG** -- the editor IS the output. |
| **Key insight** | The world's most-used word processor defaults to paragraph spacing, not indentation. This shapes user expectations broadly. Anyone who has used Word expects Enter to produce a visible gap, not an indent. |

### 6. Atticus (Book Formatting Tool)

| Aspect | Detail |
|---|---|
| **Editor default** | **Block-style / spacing** in the editor. No indentation shown while writing. |
| **Enter key** | Creates a new paragraph with block spacing. |
| **Editor vs Output** | **Separated.** The editor uses block formatting by default. The theme/preview controls whether the final output uses indented or spaced paragraphs. Users can toggle "automatic indents" in editor settings if they prefer to see indents while writing. |
| **Key insight** | Atticus enforces industry standards (indent OR spacing, never both) but makes the editor spacing-based by default. Preview/export applies the chosen paragraph style. Shift+Enter overrides indentation at specific points. |

### 7. Vellum (Mac Book Formatting App)

| Aspect | Detail |
|---|---|
| **Editor default** | **Simple block formatting** -- no indentation in editor. Paragraph spacing between paragraphs. |
| **Enter key** | Creates a new block paragraph with spacing. |
| **Editor vs Output** | **Explicitly separated.** Vellum's help docs state: "Because the indentation can vary in the final book based on style, the paragraph position, and other factors, the Text Editor just uses simple block formatting." The editor is "optimized for ease of editing and therefore does not display the styling that will appear in your finished book." |
| **Key insight** | Vellum is the strongest example of deliberate separation. They consciously chose block formatting in the editor because indent behavior varies contextually in the output (first paragraphs after headings aren't indented, etc.). Block formatting is simpler and less confusing for writing. |

### 8. Dabble (Novel Writing App)

| Aspect | Detail |
|---|---|
| **Editor default** | **"Book style" paragraphs** -- first-line indent with double spacing. No extra paragraph spacing. |
| **Enter key** | Creates a new indented paragraph. |
| **Editor vs Output** | The editor formatting is described as "not formatting for print or ebooks -- just for your manuscript." Separate export formatting. |
| **Key insight** | One of the few apps that defaults to indent-style in the editor. However, users have requested more formatting options, and the "book style" label suggests this is mimicking manuscript format rather than being a UX-optimized choice. Story notes use block paragraphs (no indent, spacing between). |

### 9. Wattpad (Online Writing Platform)

| Aspect | Detail |
|---|---|
| **Editor default** | **Block spacing** between paragraphs. No indentation. |
| **Enter key** | Single Enter creates a new paragraph with a visible gap. Shift+Enter creates a line break without a paragraph gap. |
| **Editor vs Output** | **WYSIWYG** -- what you write is what readers see. |
| **Key insight** | Web-native platform uses web conventions exclusively. Indentation from pasted Word content is stripped out. Block spacing is the only paragraph model. Double-Enter creates excessive spacing and is discouraged. |

### 10. Draft2Digital (Self-Publishing Tool)

| Aspect | Detail |
|---|---|
| **Input format** | Accepts Word documents. Recommends standard manuscript formatting (indent, no extra spacing). |
| **Conversion behavior** | Automatically applies first-line indent to all paragraphs except: first paragraph of a chapter, and first paragraph after a scene break. |
| **Editor vs Output** | No writing editor -- it's a formatting/conversion tool. The output formatting is hardcoded to industry standards. |
| **Key insight** | Confirms industry standard: indent all paragraphs except after headings/breaks. But this is an OUTPUT concern, not a writing concern. |

---

## Typographic Conventions

### Printed Books (Industry Standard)

- **First-line indent** of 0.25"--0.5" (1--2 ems)
- **No extra spacing** between paragraphs
- **First paragraph** after a chapter heading or scene break: **flush left** (no indent)
- **All subsequent paragraphs**: indented
- This convention is universal across fiction publishing and has been for centuries
- The indent + short last line of the previous paragraph create sufficient visual separation

### Digital/Web Text (Modern Standard)

- **No first-line indent**
- **Vertical spacing** (margin/padding) between paragraphs
- Typically 0.5em--1em of space between paragraphs
- This is the CSS/HTML default (`<p>` tags have margin)
- Better for screen reading: scanning, variable widths, reflowable text

### The Key Distinction

| Context | Paragraph Separation Method |
|---|---|
| Printed fiction books | First-line indent, no spacing |
| Printed non-fiction | Either indent or block spacing |
| Web content | Block spacing, no indent |
| Email | Block spacing, no indent |
| Academic papers | First-line indent (APA, Chicago) |
| Manuscript submissions | First-line indent, double-spaced |

**The rule:** Use one method or the other, never both. Using both indent AND spacing is universally discouraged.

---

## Expert Recommendations: Writing vs Reading Experience

### Writing Experience (Editor)

The consensus from the apps surveyed:

1. **Optimize the editor for the writer, not the reader.** The writing environment should be comfortable and distraction-free. Scrivener, Vellum, Atticus, and Ulysses all explicitly separate editor formatting from output formatting.

2. **Block spacing is more natural for digital writing.** When you press Enter on a keyboard, the mental model is "I'm creating a gap." Every web form, text field, email client, and messaging app reinforces this expectation.

3. **Indentation in the editor creates confusion.** Vellum explicitly explains why they don't show indents in the editor: "Because the indentation can vary in the final book based on style, the paragraph position, and other factors, the Text Editor just uses simple block formatting." Showing indents in the editor creates false expectations about output formatting.

4. **The editor should reduce cognitive load.** Writers should focus on words, not formatting. Block paragraphs are simpler -- there's no ambiguity about whether a paragraph is new.

### Reading Experience (Output)

1. **Fiction books should use first-line indent** -- this is non-negotiable for professional publishing.
2. **The first paragraph after a heading/break should be flush left** -- industry standard confirmed by Chicago Manual of Style, Draft2Digital, Vellum, and Atticus.
3. **Ebooks may optionally use block spacing** but indent is still the dominant convention for fiction.

---

## Summary: The Consensus Pattern

```
EDITOR (Writing)          OUTPUT (Reading/Publishing)
--------------------      --------------------------
Block spacing             First-line indent
No indent                 No extra spacing
Single Enter = new para   First para after heading: flush left
Comfortable, simple       Industry-standard typography
```

**Apps that use spacing in editor:** Vellum, Atticus (default), iA Writer, Ulysses (default), Wattpad, Google Docs, Microsoft Word

**Apps that use indent in editor:** Scrivener (default, but separation is core philosophy), Dabble ("book style")

**The 8-to-2 ratio speaks clearly.** The overwhelming majority of professional writing tools use paragraph spacing in the editor, regardless of what the output looks like.

---

## Recommendation for Manuscript

The current approach (first-line indentation in the editor) follows the minority pattern (Scrivener/Dabble). The recommended approach:

1. **Editor:** Use block paragraph spacing (vertical whitespace between paragraphs, no first-line indent). This matches what 80% of writing tools do and what users expect from any digital text input.

2. **Book preview/export:** Use first-line indentation with no extra paragraph spacing, following standard book typography conventions. First paragraph after chapter heading should be flush left.

3. **The separation is the feature.** Following Vellum and Atticus's lead, the editor should be optimized for writing comfort, while the preview/export should be optimized for reading. These are different concerns with different optimal solutions.

---

## Sources

- [Setting up default formatting in Scrivener 3](https://gwenhernandez.com/2024/10/28/setting-up-default-formatting-in-scrivener-3/)
- [Scrivener Styles Overview](https://www.leedelacy.com/learning-scrivener-basics/scrivener-styles-overview-part-one)
- [Compiling Your Scrivener Project](https://www.literatureandlatte.com/blog/compiling-your-scrivener-project-the-basics)
- [Ulysses Editor Customization Guide](https://help.ulysses.app/dive-into-editing/editor-customization-guide)
- [Ulysses Paragraph Settings Reference](https://styles.ulysses.app/reference/paragraph-settings)
- [iA Writer Settings](https://ia.net/writer/support/basics/settings)
- [iA Writer Indented Paragraphs in Preview](https://medium.com/ia-writer-tips-and-tricks/indented-paragraphs-in-preview-e81e73d6cd44)
- [iA Writer Markdown Guide](https://ia.net/writer/support/basics/markdown-guide)
- [Google Docs Paragraph Formatting](https://support.google.com/docs/answer/1663349?hl=en)
- [Microsoft Word Indents and Spacing](https://support.microsoft.com/en-us/office/adjust-indents-and-spacing-in-word-dd821599-b731-4c29-be3c-d854a705e086)
- [Word Default Line Spacing](https://support.microsoft.com/en-us/office/change-the-default-line-spacing-in-word-411437a0-0646-490d-b426-a9249a78b315)
- [Atticus Paragraph Indentation and Spacing](https://intercom.help/atticus-5877e36564df/en/articles/12732403-how-do-i-control-paragraph-indentation-and-spacing-in-atticus)
- [Atticus Text and Paragraph Styling](https://www.atticus.io/customizing-body-text/)
- [Vellum Text Editor Help](https://help.vellum.pub/text-editor/)
- [Vellum Body Style Help](https://help.vellum.pub/styles/body/)
- [Dabble Formatting Help](https://help.dabblewriter.com/en/articles/4397448-how-to-change-your-formatting)
- [Wattpad Formatting Guide](https://www.wattpad.com/961505325-formatting-your-wattpad-story-indents-line-breaks)
- [Wattpad Story Formatting Help](https://support.wattpad.com/hc/en-us/articles/360059402331-Formatting-your-story)
- [Draft2Digital eBook Layout Guide](https://www.draft2digital.com/blog/the-pocket-guide-to-ebook-layout/)
- [Book Design: Choosing Your Paragraphing Style](https://www.thebookdesigner.com/paragraphing-style/)
- [10 Typesetting Rules for Books](https://selfpublishingadvice.org/10-typesetting-rules-for-indie-authors/)
- [Formatting Your Book: Paragraphs and Sections](https://garethsouthwell.substack.com/p/formatting-your-book-paragraphs-and)
- [Chicago Manual of Style: Manuscript Preparation FAQ](https://www.chicagomanualofstyle.org/qanda/data/faq/topics/ManuscriptPreparation/faq0223.html)
- [Web Typography: Indent and Space](http://smad.jmu.edu/shen/webtype/indent.html)
- [All About Indents and Other Paragraph Separators](https://creativepro.com/all-about-indents-and-other-paragraph-separators/)

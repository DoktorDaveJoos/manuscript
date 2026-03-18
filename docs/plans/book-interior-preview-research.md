# Book Interior Preview Rendering: Deep Research

## 1. PDF Preview Rendering Approaches

### 1.1 How Existing Tools Handle Preview

**Atticus** is built as a Progressive Web App. Its preview re-renders the entire formatted file on every settings change to produce a pixel-accurate representation of the print output. It supports previewing across simulated device types (Kindle, iPad, etc.) for EPUB, and a true-to-export print preview for PDF. The full re-render approach means previewing is slower for large manuscripts but guarantees WYSIWYG fidelity.

**Vellum** (macOS-only native app) uses Apple's Core Text typesetting engine under the hood, which gives it access to the same high-quality text layout used by professional publishing tools. Its live preview updates in real time as you tweak settings. Because it's native rather than web-based, it avoids browser typography limitations entirely.

**Reedsy Studio** is a free browser-based editor that uses "operational transformation" for collaborative editing and automated server-side typesetting. When you export, Reedsy runs a server-side typesetting engine that produces professional EPUB and print-ready PDF. The in-editor preview is a simplified representation; the final typeset output is generated on export.

**Scrivener** compiles manuscripts through a "Compile" pipeline where content is transformed according to format presets. PDF output goes through the OS print subsystem (macOS's Core Text / Windows's print drivers). There is no true live preview -- you compile to see the result.

**Overleaf** (LaTeX editor) compiles LaTeX on the server using TeX Live, generating a real PDF. The browser then displays a rasterized image of each PDF page via their custom viewer (not raw PDF rendering). SyncTeX provides bidirectional navigation between source and output.

**Google Docs** uses a custom canvas-based rendering engine (Kix) that draws text on HTML5 canvas rather than relying on DOM text layout. This gives them pixel-perfect control over typography but requires reimplementing everything from scratch.

### 1.2 Common Architectural Approaches

There are three primary approaches to book interior preview in web applications:

#### Approach A: Server-Side PDF Generation + Viewer
- Generate actual PDF on the server (using LaTeX, Prince XML, WeasyPrint, etc.)
- Display the result using pdf.js or a similar viewer
- **Pros:** Output matches the final export exactly; proven typesetting quality
- **Cons:** Requires server round-trip; latency on every change; infrastructure cost
- **Used by:** Overleaf, Reedsy (for final output)

#### Approach B: CSS Paged Media in the Browser (Paged.js / Vivliostyle)
- Use a JavaScript polyfill to implement CSS Paged Media spec in the browser
- Content is chunked into pages client-side using CSS columns for fragmentation
- **Pros:** No server needed; uses standard HTML/CSS; live preview possible; output can be printed to PDF via browser print dialog
- **Cons:** Performance degrades with large documents; browser typography limitations; CSS scoping challenges in SPAs
- **Used by:** CSS-based publishing workflows, Electric Book Works

#### Approach C: Custom React/JS Pagination (Current Manuscript Approach)
- Build a custom pagination engine that estimates line counts and splits content into page-sized chunks
- Render each page as a React component
- **Pros:** Full control; lightweight; fast for small/medium documents; no external dependencies; easy to style
- **Cons:** Pagination is approximate (character-count estimation vs. actual text reflow); doesn't account for variable-width fonts, ligatures, or browser rendering differences; diverges from actual export output
- **Used by:** Manuscript (current implementation)

#### Approach D: React-PDF (@react-pdf/renderer)
- Define PDF layout using React components with a custom renderer
- Generates actual PDF documents in the browser or server
- **Pros:** React-native API; built-in page breaks, widows/orphans protection, bookmarks
- **Cons:** Cannot reuse DOM-targeting components (Material-UI, Tailwind rendered elements); requires separate component tree for PDF; limited CSS support (Flexbox only, no CSS Grid)
- **Used by:** Invoice generators, report builders

### 1.3 HTML-to-PDF Libraries Comparison

| Tool | Type | CSS Paged Media | JS Support | Quality | Speed | Cost |
|------|------|----------------|------------|---------|-------|------|
| **Prince XML** | Server | Excellent (best) | Yes | Highest | Fast | ~$3,800/license |
| **PDFreactor** | Server | Excellent | Yes | Highest | Fast | Commercial |
| **WeasyPrint** | Server (Python) | Good (subset) | No | Good | Moderate | Free/OSS |
| **Puppeteer/Playwright** | Headless browser | Basic (@page size/margin) | Full | Good | Slow | Free/OSS |
| **Paged.js** | Client-side polyfill | Good | Full (browser) | Good | Varies | Free/OSS |
| **Vivliostyle** | Client + CLI | Good | Full (browser) | Good | Varies | Free/OSS |
| **@react-pdf/renderer** | Client/Server | None (custom API) | N/A | Good | Fast | Free/OSS |
| **Typeset.sh** | Server | Good | No | Good | Fast | Commercial |

### 1.4 NativePHP / Electron Considerations

Since Manuscript is a NativePHP (Electron) desktop app, there's a unique opportunity:

- **`webContents.printToPDF()`**: Electron's built-in method converts any BrowserWindow content to PDF. This means you can render HTML/CSS in a hidden BrowserWindow and capture it as a real PDF.
- **Hidden BrowserWindow pattern**: Render Paged.js or CSS Paged Media content in a hidden Electron window, then either capture as PDF or render page images back to the main window.
- **No server dependency**: All processing happens locally, avoiding the latency of server-side generation.


## 2. CSS Paged Media Specification

### 2.1 @page Rules

```css
/* Basic page setup */
@page {
  size: 6in 9in;           /* trim size */
  margin: 0.75in 0.625in;  /* top/bottom and left/right */
  marks: crop;              /* crop marks for printing */
  bleed: 0.125in;           /* bleed area */
}

/* Different margins for left/right pages (recto/verso) */
@page :left {
  margin-left: 0.875in;    /* gutter side (binding) */
  margin-right: 0.625in;
}

@page :right {
  margin-left: 0.625in;
  margin-right: 0.875in;   /* gutter side (binding) */
}

/* First page of a section */
@page :first {
  margin-top: 2in;         /* extra top margin for chapter openings */
}

/* Blank pages inserted by forced breaks */
@page :blank {
  @top-center { content: none; }
  @bottom-center { content: none; }
}
```

### 2.2 Named Pages

```css
/* Define a named page for chapters */
@page chapter {
  @top-center {
    content: string(chapter-title);
  }
  @bottom-center {
    content: counter(page);
  }
}

@page chapter:first {
  @top-center { content: none; }  /* No header on first page of chapter */
}

/* Apply named page to elements */
.chapter {
  page: chapter;
  break-before: right;  /* Always start on a right-hand page */
}
```

### 2.3 Margin Boxes (16 regions)

The spec defines 16 margin box regions around each page:

```
@top-left-corner  |  @top-left  |  @top-center  |  @top-right  |  @top-right-corner
@left-top         |             |               |              |  @right-top
@left-middle      |             |  [content]    |              |  @right-middle
@left-bottom      |             |               |              |  @right-bottom
@bottom-left-corner | @bottom-left | @bottom-center | @bottom-right | @bottom-right-corner
```

### 2.4 Running Headers/Footers

Two mechanisms exist for running headers:

**Named Strings** (simpler, text-only):
```css
h2.chapter-title {
  string-set: chapter-title content();
}

@page {
  @top-center {
    content: string(chapter-title);  /* Updates when a new h2 appears */
  }
}
```

**Running Elements** (richer, supports arbitrary HTML):
```css
.running-header {
  position: running(headerContent);
}

@page {
  @top-left {
    content: element(headerContent);
  }
}
```

### 2.5 Page Counters

```css
@page {
  @bottom-center {
    content: counter(page);
  }
}

/* Reset counter for front matter (Roman numerals) */
.frontmatter {
  counter-reset: page 1;
}

@page frontmatter {
  @bottom-center {
    content: counter(page, lower-roman);
  }
}
```

### 2.6 Fragmentation Control

```css
/* Break properties */
h2 { break-before: right; }          /* Start on right-hand page */
h3 { break-after: avoid; }           /* Don't break right after a heading */
figure { break-inside: avoid; }      /* Keep figures together */
.chapter { break-before: recto; }    /* Start on recto (right in LTR) */

/* Widows and orphans */
p {
  widows: 2;    /* Minimum lines at top of new page */
  orphans: 2;   /* Minimum lines at bottom of page before break */
}
```

### 2.7 Browser Support Reality

**Natively supported by browsers:**
- `@page { size; margin }` -- Chrome, Firefox, Safari
- `break-before`, `break-after`, `break-inside` -- partial support
- `widows`, `orphans` -- Chrome, Opera (NOT Firefox, NOT Safari)
- `@page :first` -- Chrome, Firefox

**NOT natively supported (requires polyfill like Paged.js):**
- Margin boxes (@top-center, etc.)
- Named pages
- Running headers/footers (string-set, running())
- Page counters in margins
- `@page :left / :right` selectors (partial)
- `bleed` and `marks`


## 3. Paged.js Deep Dive

### 3.1 Architecture

Paged.js consists of three core modules:

1. **Polisher**: Parses CSS stylesheets, extracts `@page` rules and paged media declarations, and transforms them into browser-understandable CSS. It replaces unsupported declarations with JavaScript-driven implementations.

2. **Chunker**: The pagination engine. Takes rendered content and fragments it into discrete pages using CSS columns for overflow detection:
   - Places content into a page-sized container
   - Detects overflow using CSS column fragmentation
   - Creates a "break token" at the overflow point
   - Moves remaining content to the next page container
   - Repeats until no content remains

3. **Previewer**: Orchestrates the Polisher and Chunker, manages the preview lifecycle, and provides the public API.

### 3.2 How the Chunker Works (Algorithm)

```
1. Chunker.flow() is called
2. For each page:
   a. Create a page container with correct dimensions from @page rules
   b. Insert content into the container
   c. The browser's CSS column layout naturally causes overflow
   d. Chunker.findBreakToken() detects where overflow occurs
   e. Content after the break point is removed from this page
   f. A BreakToken stores the position for the next page
   g. Layout hooks fire (renderNode, onOverflow, afterOverflowRemoved)
3. Process continues until all content is placed
4. afterRendered hook fires with all pages
```

The key insight: Paged.js relies on the browser's own CSS column fragmentation to determine where content breaks. It doesn't calculate line heights or character counts -- it lets the browser do the actual text layout, then detects the overflow point. This means pagination accuracy matches what the browser would actually render.

### 3.3 Hooks System

Paged.js provides hooks at every stage of the rendering pipeline:

**Previewer hooks:**
- `beforePreview` -- before any processing starts
- `afterPreview` -- after all pages are rendered

**Chunker hooks:**
- `beforeParsed(content)` -- content before parsing/ID assignment
- `afterParsed(parsed)` -- content parsed but not yet rendered
- `beforePageLayout(page)` -- before a page is laid out
- `onPageLayout(page)` -- during page layout
- `afterPageLayout(page, breakToken)` -- after a page is complete
- `finalizePage(page, fragment)` -- final adjustments to a page
- `afterRendered(pages)` -- all pages complete

**Layout hooks:**
- `layoutNode(node)` -- each node during layout
- `renderNode(node, sourceNode)` -- when a node is rendered to a page
- `onOverflow(overflow, rendered, breakToken)` -- when content overflows
- `onBreakToken(breakToken, overflow, rendered)` -- when a break is created
- `afterOverflowRemoved(removed, rendered)` -- after overflow content is moved

**Polisher hooks:**
- `beforeTreeParse(text, sheet)` -- raw CSS text before parsing
- `onAtPage(node, item, list)` -- each @page rule
- `onRule(node, item, list)` -- each CSS rule
- `onDeclaration(declaration, dItem, dList, rule)` -- each CSS declaration
- `onContent(funcNode, fItem, fList, declaration)` -- content() functions

### 3.4 Custom Handler Example

```javascript
import { Handler } from 'pagedjs';

class MyHandler extends Handler {
  constructor(chunker, polisher, caller) {
    super(chunker, polisher, caller);
  }

  afterParsed(parsed) {
    // Modify content before pagination begins
    console.log('Content parsed, about to paginate');
  }

  afterPageLayout(pageFragment, page, breakToken) {
    // Add custom elements to each page after layout
    const pageNum = page.element.querySelector('.pagedjs_page_content');
    // ... modify page content
  }

  afterRendered(pages) {
    console.log(`Total pages: ${pages.length}`);
  }
}

Paged.registerHandlers(MyHandler);
```

### 3.5 Using the Previewer API

```javascript
import { Previewer } from 'pagedjs';

const previewer = new Previewer();

// preview(content, stylesheets, renderTo) -> Promise<Flow>
const flow = await previewer.preview(
  document.querySelector('#content'),  // DOM content to paginate
  ['/styles/book.css'],                // CSS files to apply
  document.querySelector('#preview')   // Container to render into
);

console.log(flow.total);  // Total number of pages
console.log(flow.performance); // Rendering performance data
```

### 3.6 React Integration Patterns

**Pattern 1: useEffect with Previewer (simplest)**
```jsx
function BookPreview({ content, css }) {
  const containerRef = useRef(null);

  useEffect(() => {
    const previewer = new Previewer();
    const contentEl = document.createElement('div');
    contentEl.innerHTML = content;

    previewer.preview(contentEl, [css], containerRef.current)
      .then(flow => console.log(`${flow.total} pages`));

    return () => {
      // Cleanup: remove generated pages
      if (containerRef.current) {
        containerRef.current.innerHTML = '';
      }
    };
  }, [content, css]);

  return <div ref={containerRef} />;
}
```

**Pattern 2: iframe Isolation (recommended for SPAs)**
```jsx
function IsolatedPreview({ content, styles }) {
  const iframeRef = useRef(null);

  useEffect(() => {
    const iframe = iframeRef.current;
    const doc = iframe.contentDocument;

    doc.open();
    doc.write(`
      <html>
        <head>
          <style>${styles}</style>
          <script src="/pagedjs/paged.polyfill.js"></script>
        </head>
        <body>${content}</body>
      </html>
    `);
    doc.close();
  }, [content, styles]);

  return <iframe ref={iframeRef} style={{ width: '100%', height: '100%' }} />;
}
```

The iframe approach is strongly recommended because Paged.js collects all `@page` CSS and applies it globally, which breaks other elements in a SPA. An iframe provides complete CSS isolation.

**Pattern 3: Debounced Re-rendering**
```jsx
function LivePreview({ content }) {
  const [debouncedContent] = useDebounce(content, 500);
  const containerRef = useRef(null);
  const previewerRef = useRef(null);

  useEffect(() => {
    // Destroy previous preview
    if (containerRef.current) {
      containerRef.current.innerHTML = '';
    }

    const previewer = new Previewer();
    previewerRef.current = previewer;

    const el = document.createElement('div');
    el.innerHTML = debouncedContent;

    previewer.preview(el, ['/book.css'], containerRef.current);
  }, [debouncedContent]);

  return <div ref={containerRef} />;
}
```

### 3.7 Performance Characteristics and Limitations

**Performance:**
- Documents under ~50 pages: renders in < 1 second
- Documents of 100-200 pages: 2-5 seconds
- Documents of 300+ pages: can take 10+ seconds and generate 14+ MB of DOM
- Each page creates a full DOM subtree with grid layout for margins
- Re-rendering requires destroying and recreating the entire page tree
- No incremental/partial re-rendering support

**Known Limitations:**
- CSS scoping: all `@page` styles are applied globally (problematic in SPAs)
- No built-in cleanup/destroy API -- you must manually clear the container
- Re-rendering on content change requires full re-pagination
- Performance issues with Chromium headless (rendering can start before Paged.js finishes)
- Limited progress on bug fixes and releases (community concern noted in 2022-2023)
- Figures and complex elements can cause layout issues near page breaks
- Memory grows linearly with page count (each page is a full DOM tree)

**Mitigations:**
- Use iframe isolation to prevent CSS leakage
- Debounce content changes (500ms+) before re-rendering
- For very large documents, split content into chapter-level chunks
- Consider rendering only visible pages and using thumbnail placeholders for others
- Use Web Workers for content preprocessing (HTML parsing, etc.)


## 4. EPUB Preview Rendering

### 4.1 EPUB Architecture

An EPUB file is essentially a ZIP archive containing:
- XHTML/HTML5 content documents
- CSS stylesheets
- Images and fonts
- OPF manifest (metadata, spine order)
- NCX/Navigation document (table of contents)

There are two layout types:
- **Reflowable**: Content reflows like a web page; reader controls font size, margins
- **Fixed Layout (FXL)**: Pixel-perfect positioning, like a PDF; used for comics, children's books

### 4.2 Web-Based EPUB Rendering Libraries

**epub.js** (futurepress/epub.js):
- Most popular web EPUB renderer
- Uses CSS multi-column for pagination in "paginated" mode
- Each column = one page; horizontal scrolling moves between pages
- Supports reflowable and fixed-layout EPUBs
- Two managers: "default" (single section) and "continuous" (seamless scrolling)
- Renders each EPUB section in an iframe for CSS isolation
- API: `Book.renderTo(element, { width, height, flow: 'paginated' })`

**Foliate-js** (johnfactotum/foliate-js):
- Lighter alternative with modular architecture
- Separate modules for book parsing ("book" interface) and pagination ("renderer" interface)
- Also uses CSS multi-column for pagination (same strategy as epub.js)
- Acknowledged limitations: slow pagination, some CSS styles don't work as expected
- Supports EPUB, MOBI, KF8, FB2, CBZ formats

**Readium** (readium-js):
- Three components: readium-js (core), readium-shared-js (rendering), readium-js-viewer (UI)
- Uses CSS multi-column for pagination
- Readium CSS is a separate project for reading system stylesheets
- Supports both reflowable and fixed-layout EPUBs
- Fixed layout: no user style injection; maintains visual fidelity
- More suitable for complex enterprise requirements

### 4.3 CSS Multi-Column Pagination Strategy

All three major EPUB renderers use the same core technique:

```css
/* The container is sized to one "page" */
.epub-container {
  width: 400px;
  height: 600px;
  overflow: hidden;
  column-width: 400px;
  column-gap: 0;
}

/* Content flows into columns, each column = a page */
/* Horizontal translation moves between "pages" */
.epub-container {
  transform: translateX(-800px); /* Show page 3 (0-indexed) */
}
```

The content naturally flows into CSS columns. Each column is the width of one page. To navigate between pages, the container's scroll position or transform is adjusted to show different columns. Total page count = container's scrollWidth / pageWidth.

### 4.4 How Book Writing Tools Preview EPUB

Most tools take one of two approaches:

1. **Simulated preview**: Render content in a container styled to approximate an e-reader's display, without actually generating an EPUB. Font size, margins, and basic formatting are shown. This is fast but approximate. (Atticus does this for device previews.)

2. **Actual EPUB rendering**: Generate the EPUB in memory, then render it using epub.js or similar. This is accurate but slower, especially for real-time preview during editing.

For a book writing tool, the simulated approach is generally preferred during editing (fast feedback), with actual EPUB generation on export.


## 5. Best Practices for Book Interior Preview

### 5.1 Typography

**Justified Text:**
```css
p {
  text-align: justify;
  hyphens: auto;
  -webkit-hyphens: auto;
  hyphenate-limit-chars: 6 3 2;  /* min word length, before break, after break */
  /* hyphenate-limit-lines: 2;   Safari only with prefix */
}
```

The `hyphenate-limit-chars: 6 3 2` rule means: only hyphenate words with 6+ characters, leaving at least 3 characters before the hyphen and 2 after. This prevents ugly short fragments.

**Browser support reality for hyphenation:**
- `hyphens: auto` -- Chrome (with lang attribute), Firefox, Safari
- `hyphenate-limit-chars` -- Chrome 109+, Edge (NOT Firefox, Safari uses `-webkit-hyphenate-limit-before/after`)
- `hyphenate-limit-lines` -- Safari only (with prefix)
- Hyphenation requires the `lang` attribute on the HTML element

**Widows and Orphans:**
```css
p {
  widows: 2;   /* Chrome, Opera -- NOT Firefox, NOT Safari */
  orphans: 2;  /* Chrome, Opera -- NOT Firefox, NOT Safari */
}
```

Firefox and Safari have never implemented widows/orphans control (since the property was introduced in 1997). Paged.js and Vivliostyle handle this through their JavaScript polyfill layer.

### 5.2 Drop Caps

```css
/* Classic approach -- widely supported */
.chapter-first-paragraph::first-letter {
  float: left;
  font-size: 3.5em;
  line-height: 0.8;
  margin-right: 0.05em;
  margin-top: 0.05em;
}

/* Modern approach -- Chrome 110+, Safari */
.chapter-first-paragraph::first-letter {
  initial-letter: 3;  /* Spans 3 lines */
}
```

The `initial-letter` CSS property is the proper solution but browser support is limited (Chrome 110+, Safari). The `::first-letter` with `float: left` approach works everywhere and is what most web-based book tools use.

**Important caveat for EPUB:** In e-books, readers can change fonts and sizes, so drop cap styling may look inconsistent across devices. Most EPUB formatters use CSS-only drop caps rather than image-based ones for accessibility.

### 5.3 Page Layout Simulation

**Trim size to CSS dimensions:**
CSS uses 96 DPI for absolute units. 1 inch = 96px in CSS.

| Trim Size | CSS Width | CSS Height |
|-----------|-----------|------------|
| 5" x 8" | 480px | 768px |
| 5.25" x 8" | 504px | 768px |
| 5.5" x 8.5" | 528px | 816px |
| 6" x 9" | 576px | 864px |

For preview display, you typically scale these down to fit your UI:
```css
@page {
  size: 6in 9in;
  margin: 0.75in 0.625in 0.75in 0.875in;  /* top right bottom left (gutter) */
}
```

**Gutter (binding margin):**
For perfect-bound books, the inside margin (gutter) should be larger than the outside margin. Typical values:
- Inside (gutter): 0.75" - 1.0"
- Outside: 0.5" - 0.75"
- Top: 0.625" - 0.875"
- Bottom: 0.75" - 1.0"

### 5.4 Chapter Opening Pages

Professional book interiors typically have these conventions:
- Chapters start on recto (right-hand) pages: `break-before: recto;`
- Extra top margin (called "sink") on chapter opening pages -- typically 1/3 to 1/2 down the page
- Chapter number above the title (often in small caps or tracked uppercase)
- Drop cap on the first paragraph
- First line of first paragraph often set in small caps
- No running header on chapter opening pages
- Page number in footer (or suppressed entirely on chapter openers)

```css
.chapter {
  break-before: recto;
  page: chapter;
}

@page chapter:first {
  margin-top: 3.5in;  /* Deep sink */
  @top-center { content: none; }
  @top-left { content: none; }
  @top-right { content: none; }
}
```

### 5.5 Running Headers/Footers

Standard conventions:
- **Verso (left) page header:** Book title or author name
- **Recto (right) page header:** Chapter title
- **No header on:** chapter opening pages, blank pages, front matter
- **Page numbers:** typically in header corners or footer center

```css
h2.chapter-title {
  string-set: chapterTitle content();
}

@page :left {
  @top-left { content: "Book Title"; }
  @top-right { content: counter(page); }
}

@page :right {
  @top-left { content: counter(page); }
  @top-right { content: string(chapterTitle); }
}
```

### 5.6 Front Matter / Back Matter

Standard front matter order:
1. Half-title page (book title only, no subtitle/author)
2. Title page (full title, subtitle, author)
3. Copyright page
4. Dedication
5. Table of Contents
6. Foreword / Preface / Acknowledgments

Front matter typically uses lowercase Roman numeral page numbering (i, ii, iii...). Body text restarts at page 1 with Arabic numerals.

Back matter typically includes:
1. Acknowledgments (if not in front)
2. About the Author
3. Also By (other works)
4. Glossary / Index (non-fiction)

### 5.7 Real-Time vs On-Demand Preview

| Approach | Latency | Fidelity | Complexity |
|----------|---------|----------|------------|
| **Real-time custom pagination** (current Manuscript) | ~0ms | Approximate | Low |
| **Debounced Paged.js** (500ms delay) | 500ms-5s | High | Medium |
| **On-demand generation** (button click) | 1-10s | Exact | High |
| **Hybrid** (custom for editing, Paged.js for export preview) | Varies | Both | Medium-High |

The hybrid approach is often the best balance: show a fast approximate preview during editing, then use Paged.js or actual PDF generation for the export preview panel where users are reviewing final output.


## 6. Performance and Optimization Strategies

### 6.1 Pagination Strategies for Large Manuscripts

**Virtual Scrolling for Preview Pages:**
Rather than rendering all pages to the DOM, render only visible pages plus a buffer:

```jsx
function VirtualPageList({ pages, pageHeight }) {
  const [visibleRange, setVisibleRange] = useState({ start: 0, end: 5 });
  const containerRef = useRef(null);

  const onScroll = () => {
    const scrollTop = containerRef.current.scrollTop;
    const start = Math.floor(scrollTop / (pageHeight + GAP));
    const end = start + Math.ceil(containerRef.current.clientHeight / (pageHeight + GAP)) + 1;
    setVisibleRange({ start, end: Math.min(end, pages.length) });
  };

  return (
    <div ref={containerRef} onScroll={onScroll} style={{ height: '100%', overflow: 'auto' }}>
      <div style={{ height: pages.length * (pageHeight + GAP) }}>
        {pages.slice(visibleRange.start, visibleRange.end).map((page, i) => (
          <div key={i} style={{ position: 'absolute', top: (visibleRange.start + i) * (pageHeight + GAP) }}>
            <PageComponent page={page} />
          </div>
        ))}
      </div>
    </div>
  );
}
```

**Chapter-Level Chunking:**
For Paged.js, process chapters independently to avoid processing the entire manuscript at once. Cache each chapter's pagination result and only re-process changed chapters.

**Offscreen Rendering:**
Use a hidden container or offscreen iframe for pagination, then capture rendered pages as images or lightweight DOM snapshots for the scrollable preview.

### 6.2 Memory Management

- **DOM cleanup:** Each Paged.js page creates a full DOM subtree with CSS Grid for margin boxes. For a 300-page book, this means 300+ complex DOM trees. Clean up when pages scroll out of view.
- **Canvas thumbnails:** For navigation, render pages to canvas thumbnails rather than keeping full DOM trees alive.
- **Web Workers:** Offload HTML parsing and preprocessing to Web Workers. Paged.js itself requires DOM access and can't run in a Worker, but content preparation can.
- **Incremental updates:** Only re-paginate affected chapters when content changes, not the entire manuscript.

### 6.3 Font Loading

**The Problem:** If fonts haven't loaded when Paged.js (or any pagination engine) runs, text will be laid out with fallback fonts, resulting in incorrect page breaks. When web fonts load later, the text reflows but pages aren't re-paginated.

**Solutions:**
```javascript
// Wait for fonts before rendering
await document.fonts.ready;
const previewer = new Previewer();
await previewer.preview(content, stylesheets, container);

// Or use Font Face Observer for specific fonts
import FontFaceObserver from 'fontfaceobserver';

const serif = new FontFaceObserver('EB Garamond');
const sansSerif = new FontFaceObserver('Lato');

await Promise.all([serif.load(), sansSerif.load()]);
// Now safe to paginate
```

For Electron/NativePHP: bundle fonts locally to avoid network loading delays entirely.

### 6.4 Preview vs Export Fidelity

**Common causes of divergence:**
1. **Different rendering engines:** Browser (Blink/WebKit) vs. PDF engine (Prince/WeasyPrint) render text differently
2. **Font metrics:** Slight differences in font rendering between engines cause cumulative layout shifts over many pages
3. **CSS feature gaps:** Features used in export (Prince's advanced CSS) may not have browser equivalents
4. **Hyphenation dictionaries:** Different engines use different hyphenation dictionaries, leading to different line breaks
5. **Float/clear behavior:** Edge cases in CSS float behavior differ between engines

**Mitigation strategy for Manuscript:**
Since Manuscript is an Electron app, you can use the same rendering engine (Chromium) for both preview and export (via `printToPDF()`). This eliminates rendering engine divergence entirely. The workflow would be:
1. Render HTML+CSS with Paged.js in a hidden BrowserWindow
2. For preview: display the rendered pages directly
3. For export: call `webContents.printToPDF()` on the same rendered content
4. Same engine, same fonts, same layout = pixel-perfect match


## 7. Vivliostyle as Alternative to Paged.js

### 7.1 Overview

Vivliostyle is another CSS Paged Media engine that runs in the browser. It consists of:
- **Vivliostyle.js**: Core typesetting engine
- **Vivliostyle Viewer**: Web app for viewing paginated documents
- **Vivliostyle CLI**: Command-line tool for PDF generation (uses headless Chromium)
- **Vivliostyle Pub**: Online collaborative book editing platform

### 7.2 Comparison with Paged.js

| Feature | Paged.js | Vivliostyle |
|---------|----------|-------------|
| Pagination method | CSS columns fragmentation | CSS columns fragmentation |
| CSS Paged Media support | Good | Good (slightly broader) |
| Footnotes | Basic | Supported |
| Active development | Slower (concerns raised) | Active (Japanese community) |
| Spread view | No | Yes |
| Page slider/navigation | No | Yes (in Viewer) |
| Bundle size | ~150KB | ~500KB |
| React integration | Manual (Previewer API) | Manual (viewer embed) |
| Documentation | Good | Moderate (some Japanese-only) |

### 7.3 When to Choose Vivliostyle

- If you need footnote support
- If you want built-in spread view and page navigation
- If the larger bundle size is acceptable
- If Japanese/CJK text support is important

### 7.4 When to Choose Paged.js

- If you need a smaller footprint
- If you want more control via the hooks API
- If English documentation is important
- If you're building a custom preview UI (don't need Vivliostyle Viewer)


## 8. Recommendation for Manuscript

### 8.1 Current State Assessment

The current implementation uses a custom React-based pagination engine (`usePreviewPages`) that:
- Estimates lines per page using character count heuristics
- Splits paragraphs at word boundaries when they'd overflow
- Renders each page as a React component with hardcoded dimensions (340x473px)
- Handles front matter, chapter content, act breaks, and back matter

**Strengths of current approach:**
- Very fast (pure computation, no DOM measurement)
- No external dependencies
- Full control over rendering
- React-native (no DOM manipulation outside React)

**Weaknesses:**
- Character-count estimation doesn't account for variable-width characters, ligatures, or actual font metrics
- No real hyphenation or justified text
- No widow/orphan control
- Preview may not match export output
- Paragraph splitting at word boundaries is crude compared to proper text reflow

### 8.2 Recommended Evolution Path

**Phase 1 (Current -- already solid):** Keep the custom pagination for the export settings preview panel. It's fast, responsive, and gives users a good sense of their book's structure. The approximate pagination is acceptable for a settings panel where users are choosing formats and toggling options.

**Phase 2 (When export fidelity matters):** For actual PDF export, use Paged.js in a hidden Electron BrowserWindow:
1. Render book HTML with CSS Paged Media rules in a hidden BrowserWindow
2. Let Paged.js paginate using actual browser text layout (pixel-accurate)
3. Call `webContents.printToPDF()` for the final PDF
4. Preview and export use identical rendering = zero divergence

**Phase 3 (Enhanced preview):** If users need a more accurate preview panel:
- Render Paged.js in an iframe within the export preview sidebar
- Use debounced re-rendering (500ms) on settings changes
- Implement virtual scrolling to only render visible pages
- Cache chapter pagination results for performance

**Phase 4 (EPUB):** For EPUB export:
- Generate standard EPUB structure (XHTML + CSS + OPF manifest)
- Package as ZIP with .epub extension
- For EPUB preview, use epub.js or foliate-js to render the generated EPUB in an iframe

### 8.3 Key Technical Decisions

1. **Use Paged.js over Vivliostyle** for the pagination engine -- smaller bundle, better hooks API, sufficient CSS Paged Media support for book interiors

2. **Use iframe isolation** for Paged.js rendering to prevent CSS leakage into the main app

3. **Use `webContents.printToPDF()`** for final PDF generation -- leverages the same Chromium engine for zero preview/export divergence

4. **Bundle fonts locally** in the Electron app to eliminate font loading issues

5. **Use the hybrid approach** -- fast custom pagination for the settings preview, Paged.js for accurate export preview and PDF generation


---

## Sources

### Paged.js
- [Paged.js GitHub Repository](https://github.com/pagedjs/pagedjs)
- [Paged.js Documentation -- The Big Picture](https://pagedjs.org/en/documentation/1-the-big-picture/)
- [Paged.js -- How Paged.js Works](https://pagedjs.org/en/documentation/4-how-paged.js-works/)
- [Paged.js -- Generated Content in Margin Boxes](https://pagedjs.org/en/documentation/7-generated-content-in-margin-boxes/)
- [Paged.js -- Handlers, Hooks and Custom JavaScript](https://pagedjs.org/en/documentation/10-handlers-hooks-and-custom-javascript/)
- [Paged.js -- Web Design for Print](https://pagedjs.org/en/documentation/5-web-design-for-print/)
- [Paged.js Dev Docs (JSDoc)](https://pagedjs.org/devdocs/)
- [Using PagedJS with React -- Doppio.sh](https://doc.doppio.sh/article/using-pagedjs-with-react.html)
- [Mastering Paged.js: Essential Tips -- Doppio.sh](https://doppio.sh/blog/mastering-paged-js-essential-tips-for-creating-precision-pdfs-from-html)
- [react-paged: Minimal Starter for Paged.js with React](https://github.com/maaaaaaaaaaaaaaaax/react-paged)
- [Embedded PDF Preview -- Paged.js Issue #219](https://github.com/pagedjs/pagedjs/issues/219)
- [Paged.js Hacker News Discussion](https://news.ycombinator.com/item?id=21499052)
- [Paged.js and React CodeSandbox](https://codesandbox.io/s/pagedjs-and-react-xlod3)

### CSS Paged Media
- [W3C CSS Paged Media Module Level 3](https://www.w3.org/TR/css-page-3/)
- [W3C CSS Fragmentation Module Level 3](https://www.w3.org/TR/css-break-3/)
- [@page -- CSS-Tricks](https://css-tricks.com/almanac/rules/p/page/)
- [@page -- MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/CSS/@page)
- [CSS Paged Media -- MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_paged_media)
- [CSS Paged Media Module & Specifications -- DocRaptor](https://docraptor.com/css-paged-media)
- [PrintCSS: Running Headers and Footers](https://medium.com/printcss/printcss-running-headers-and-footers-3bef60a60d62)
- [PrintCSS: Page Selectors and Page Breaks](https://printcss.net/articles/page-selectors-and-page-breaks)
- [Paged Media -- Prince Documentation](https://www.princexml.com/doc/paged/)
- [Chrome Blog: Add Content to Page Margins](https://developer.chrome.com/blog/print-margins)

### Book Layout with CSS
- [Laying Out a Print Book With CSS -- Ian G McDowell](https://iangmcdowell.com/blog/posts/laying-out-a-book-with-css/)
- [Printing the Web Part 2: HTML and CSS for Printing Books](https://www.michaelperrin.fr/blog/2019/11/printing-the-web-part-2-html-and-css-for-printing-books)
- [Book Production with CSS Paged Media -- Electric Book Works](https://electricbookworks.com/thinking/book-production-with-css-paged-media/)
- [print-css.rocks -- CSS Paged Media Tutorial](https://print-css.rocks/)
- [Introduction to CSS for Paged Media -- MarkupUK](https://markupuk.org/2018/webhelp/ar06.html)

### Typography
- [All You Need to Know About Hyphenation in CSS -- Richard Rutter](https://clagnut.com/blog/2395/)
- [hyphenate-limit-chars -- MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/hyphenate-limit-chars)
- [Control Your Drop Caps with CSS initial-letter -- Chrome Blog](https://developer.chrome.com/blog/control-your-drop-caps-with-css-initial-letter)
- [Drop Caps: Historical Use and Current Best Practices -- Smashing Magazine](https://www.smashingmagazine.com/2012/04/drop-caps-historical-use-and-current-best-practices/)
- [An End to Typographic Widows on the Web -- Clagnut](https://clagnut.com/blog/2424)
- [Fix Typography Rivers, Widows, and Orphans -- Pagination.com](https://pagination.com/fix-typography-rivers-widows-orphans/)

### EPUB Rendering
- [epub.js GitHub Repository](https://github.com/futurepress/epub.js/)
- [epub.js Documentation](http://epubjs.org/documentation/0.3/)
- [foliate-js GitHub Repository](https://github.com/johnfactotum/foliate-js)
- [Readium CSS](https://readium.org/css/)
- [epub.js vs Readium.js Comparison -- Kitaboo](https://kitaboo.com/epub-js-vs-readium-js-comparison-of-epub-readers/)

### PDF Generation Tools
- [PrinceXML Alternatives -- DocRaptor](https://docraptor.com/prince-alternatives)
- [How to Generate PDFs in 2025 -- DEV Community](https://dev.to/michal_szymanowski/how-to-generate-pdfs-in-2025-26gi)
- [Top 5 Open Source PDF Generators Compared](https://wkhtmltopdf.com/top-5-open-source-pdf-generators-compared-2025/)
- [HTML to PDF Tool Comparison -- GitHub](https://github.com/CSS-Paged-Media/html2pdf-tool-comparison)
- [Beautiful PDFs from HTML -- Medium](https://ashok-khanna.medium.com/beautiful-pdfs-from-html-9a7a3c565404)
- [React-pdf (@react-pdf/renderer)](https://react-pdf.org/advanced)

### Vivliostyle
- [Vivliostyle.org](https://vivliostyle.org/)
- [Vivliostyle Documentation](https://docs.vivliostyle.org/en/)
- [Vivliostyle CSS Paged Media Implementation](https://vivliostyle.github.io/vivliostyle_doc/en/events/vivliostyle-css-paged-media-20210410/slide.html)

### Performance and Memory
- [Memory Leaks in JavaScript PDF Viewers -- Syncfusion](https://www.syncfusion.com/blogs/post/memory-leaks-in-javascript-pdf-viewer)
- [PDF Rendering Performance -- Nutrient](https://www.nutrient.io/guides/web/best-practices/performance/)
- [Performance Issues -- pagedown (Paged.js wrapper)](https://github.com/rstudio/pagedown/issues/173)
- [A Slimmer and Faster pdf.js -- Mozilla](https://blog.mozilla.org/nnethercote/2014/02/07/a-slimmer-and-faster-pdf-js/)

### Book Formatting Tools
- [Atticus.io](https://www.atticus.io/)
- [Reedsy Studio](https://reedsy.com/studio)
- [Reedsy Studio FAQ](https://reedsy.com/studio/resources/book-writing-software-faq)
- [Best Book Formatting Software 2025 Comparison](https://writersdigestonline.com/best-book-formatting-software-for-kdp-in-2025-atticus-vs-vellum-vs-reedsy-comparison/)

### NativePHP / Electron
- [NativePHP](https://nativephp.com/)
- [Generate PDF in ElectronJS -- GeeksforGeeks](https://www.geeksforgeeks.org/generate-pdf-in-electronjs/)
- [How NativePHP Works Under the Hood -- DEV Community](https://dev.to/therahul_gupta/laravel-electron-native-power-how-nativephp-works-under-the-hood-lp7)

### Book Design Standards
- [KDP Trim Size, Bleed, and Margins](https://kdp.amazon.com/en_US/help/topic/GVBQ3CMEQW3W2VL6)
- [CSS bleed Property -- CSS-Tricks](https://css-tricks.com/almanac/properties/b/bleed/)
- [Font Loading Strategies -- font-converters.com](https://font-converters.com/guides/font-loading-strategies)

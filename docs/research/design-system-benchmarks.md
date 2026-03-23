# Design System Benchmarks: Best-in-Class Writing & Editor Apps

> Research compiled 2026-03-23. Concrete findings from Linear, iA Writer, Notion, and industry best practices.

---

## 1. Linear — Best-in-Class Minimal Productivity UI

### Color Philosophy
- **Neutral foundation**: Shifted from cool blue-gray to **warm gray** in their 2024 redesign. The goal was "warm but crisp, not muddy" — they iterated extensively to find the right warmth level.
- **Core palette**: Five anchors — Indigo (accent), Woodsmoke (near-black), Oslo Gray (mid-neutral), Black Haze (light neutral), White. That is remarkably few surface colors.
- **Light mode**: Not pure `#FFFFFF` — uses off-whites/warm tints. Text and neutral icons made **darker** in light mode for stronger contrast.
- **Dark mode**: Warm dark tones, not pure black. Text and neutral icons made **lighter** in dark mode. The warmth is consistent across modes.
- **Accent**: Desaturated indigo/blue — used extremely sparingly. Conveys "calm authority." It appears only on active states, selected items, and primary CTAs. The accent never dominates; the neutral palette does the heavy lifting.

### Typography
- **Font**: Inter (body) + Inter Display (headings). Two weights of the same family.
- **Hierarchy**: header-1 at 12px/600/uppercase (section labels), header-2 at 62px/800 (hero-level), content-1 at 20px/400 (body). Very clear separation between structural labels and content.
- **Key insight**: Section labels are **small, uppercase, heavy** — they organize without competing with content. Content text is **larger, lighter weight** — it breathes.

### Spacing
- Uses a **4px base grid** with 8px as the primary increment. Dense UI areas use 4px steps; content areas use 8/16/24px.
- Compact density throughout — every pixel is intentional. There is no "loose" padding anywhere.

### Accent Color Usage
- Single desaturated indigo. Used for: active nav items, selected states, primary buttons, focus rings. Everything else is gray-on-gray.
- The accent is never used for backgrounds of large areas — only small indicators.

### Editor/App Patterns
- Sidebar width ~220-240px.
- Content-first: the main area gets maximum real estate.
- Information density is high but never cluttered because spacing is consistent.

---

## 2. iA Writer — Gold Standard for Writing Apps

### Color Philosophy
- **Background**: `#F5F6F6` (rgb 245,246,246) — not white, not gray. A barely-there cool tint. Earlier versions added subtle noise texture to feel "analog."
- **Text**: Near-black on light backgrounds. The palette is essentially: one background color, one text color, one accent (blue cursor/caret).
- **Radical minimalism**: Effectively 3 colors in the writing interface — background, text, accent. That is it. No surface hierarchy, no card backgrounds, no border tokens. The writing surface IS the app.
- **Syntax highlighting** (added later): Uses blue, yellow, orange, purple, green for parts-of-speech highlighting — but only when the user explicitly activates "Syntax Control." The default state is monochrome.
- **Dark mode**: True dark with warm text. Clean inversion.

### Typography
- **Three custom fonts**, all evolved from monospace:
  - **iA Writer Mono**: True monospace. "This text is work in progress" — the rawness signals "not done yet."
  - **iA Writer Duo**: Duospace — M, W, m, w get 150% width, everything else is mono. Flows like proportional but retains monospace's structural honesty.
  - **iA Writer Quattro**: Four character widths. "Proportional enough to save space, monospace enough to stay honest."
- **Key insight**: The font choice itself communicates the app's purpose. Monospace = drafting. Proportional = polished. iA chose the space between.
- **Variable fonts** with thousands of grades: weight adjusts by size/device/background. Font gets **thinner and tighter** as size increases. Line height adjusts with column width.
- **Line length**: Supports 64, 72, 80 characters per line. These are deliberate caps — never full-width.

### Spacing
- **Line height**: Varies with column width. Wider columns need higher line-height for the eye to track back to the start of the next line. This is dynamic, not fixed.
- **Gutter/margins**: Large. The margins are "not aesthetic — they let text breathe." Content area is deliberately narrow.
- **Key rule**: "A higher line height is needed the wider the column width."

### Accent Color
- **Blue** — used almost exclusively for the cursor/caret. That is the only persistent color in the default writing view.
- When syntax highlighting is on, accent colors appear for grammatical categories, but these are educational, not decorative.

### Writing App Patterns
- **Zero chrome**: No visible toolbar in default mode. No sidebar. No panels. Just text.
- **Focus mode**: Dims all text except the current sentence/paragraph.
- **The interface IS the typography**: Every design decision is a typography decision.

---

## 3. Notion — Editor/Productivity Hybrid

### Color Philosophy
- **Background**: Pure white (`#FFFFFF`) in light mode. Warm off-white pages.
- **Warm grays**: Notion uses warm grays rather than harsh blacks for body text. The default text is NOT `#000000` — it is a softened dark.
- **10 semantic colors**: Default (gray), Gray, Brown, Orange, Yellow, Green, Blue, Purple, Pink, Red. Each has **three variants**: text, background, and icon — with icons being more saturated than text.
- **Surface hierarchy**: Very flat. Pages are white, sidebar is slightly off-white/light gray. Minimal layering.
- **Dark mode**: Warm dark tones, not pure black. Consistent with the trend.

### Typography
- **System fonts**: SF Pro (macOS), Segoe UI (Windows). Feels native to each platform.
- **Three user-selectable styles**: Default (sans), Serif, Mono.
- **Weight usage**: Medium weight dominates the sidebar and UI chrome. Body text is regular weight.
- **Key insight**: Using system fonts means Notion feels like a native OS tool, not a web app. This is a deliberate choice for a productivity tool.

### Spacing
- **8px grid system** throughout.
- **Sidebar**: 224px wide — "wide enough to show page names, without wasting space."
- **Search bar**: 30px tall.
- **Section gaps**: 6px between sidebar groups — small enough to feel connected, large enough for the eye to register the shift.
- **Key insight**: Every measurement follows the grid. 6px is a rare exception (6 = 8 - 2), used only for the tightest visual separations.

### Accent Color
- **Blue**: Used for links, active sidebar items, and selection highlights. Notion's blue text (#487CA5) and blue icon (#337EA9) are different values — icons are more saturated.
- Accent is calm and muted — never electric or attention-grabbing.

### Editor Patterns
- **Content width**: Constrained to comfortable reading width even on wide screens. Pages have generous horizontal margins.
- **Hover states**: Subtle gray backgrounds. Never accent-colored on hover — only on active/selected.
- **Block-based editing**: Each content block has its own handle, but handles are invisible until hover.

---

## 4. Color Palette Best Practices (Neutral + Single Accent)

### How Many Grays
- **Best practice**: 9-11 steps from white to near-black, named by lightness (e.g., gray-05, gray-10, gray-20... gray-95). But in practice, most apps actively use only **5-7 grays** regularly.
- **Avoid medium grays**: Provide a few light grays and a few dark grays. Medium grays lack contrast against both light and dark backgrounds — they are the "dead zone."
- **The sweet spots**: 2-3 very light grays (surfaces, backgrounds, hovers), 1-2 medium-dark grays (secondary text, borders), 1-2 near-blacks (primary text, headings).

### Tinted vs. Pure Grays
- **Tinted grays are superior** to pure grays for product UI. Pure grays feel cold, sterile, and "digital."
- **Warm tint**: Add subtle brown/yellow/amber to grays. Creates approachable, organic feeling. Good for: creative tools, writing apps, personal productivity.
- **Cool tint**: Add subtle blue to grays. Creates professional, technical feeling. Good for: developer tools, analytics, enterprise.
- **Key rule**: "Take your main brand color temperature and go the **opposite direction** with your grays" for contrast. OR tint grays toward your brand color for cohesion. Both work — just be consistent.
- **Manuscript's approach** (warm amber-tinted grays) aligns with creative/writing tool conventions.

### Accent Color Strategy
- **One primary accent** is sufficient for most apps. Additional colors should be reserved for semantic meaning (success, warning, error, info).
- **Accent restraint**: The accent should appear on <10% of the UI surface area. If the accent is everywhere, nothing stands out.
- **Accessible contrast**: The accent must pass WCAG AA against both its typical backgrounds AND white/dark surfaces.
- **Accent tints**: Derive 2-3 lighter tints of the accent for backgrounds (e.g., accent at 10% opacity for selected row backgrounds). Never use the full accent as a background color for large areas.
- **Hover vs. active**: Hover = neutral gray shift. Active/selected = accent. This is a near-universal pattern.

### Dark Mode
- **Never pure black** (`#000000`) — use `#121212` to `#1A1A1A` range. Linear, Notion, and Apple all avoid pure black.
- **Maintain warmth/coolness**: If light grays are warm-tinted, dark grays should be too.
- **Accent shifts lighter** in dark mode for contrast (e.g., saturated orange becomes lighter peach).

---

## 5. Typography Scale Best Practices

### Ratio Selection
- **Minor Third (1.2)**: Best for text-heavy, content-dense UIs — dashboards, editors, writing apps. Provides subtle hierarchy without dramatic jumps.
- **Major Third (1.25)**: Moderate contrast. Good general-purpose choice.
- **Perfect Fourth (1.333)**: Marketing sites, landing pages. Too dramatic for dense UIs.
- **Golden Ratio (1.618)**: Display/editorial only. Far too much contrast for application UI.

### Recommended Scale for Desktop Editor Apps
Using 14px base with ~1.2 ratio:
- 11px — micro labels, metadata, badges
- 12px — secondary labels, sidebar items, small buttons
- 14px — body text, inputs, default UI text (base)
- 16px — emphasized text, panel titles
- 20px — page headings
- 24px — large headings
- 32px — display/hero (used sparingly)

This produces 7 steps, which is the **recommended maximum** for application UI. More than 7-8 sizes creates inconsistency.

### Weight Distribution
- **Three weights** is the sweet spot for most apps:
  - Regular (400) — body text, descriptions
  - Medium (500) — buttons, active states, labels
  - Semibold (600) — headings, titles
- Bold (700) should be used sparingly — only for in-content emphasis, never for UI chrome.
- **Avoid thin/light weights** (100-300) in UI — they fail at small sizes and on low-DPI screens.

### Font Pairing
- **Sans for UI, serif for content** is the gold standard for writing apps. Linear (all sans) works because it is not a writing app. iA Writer (all monospace-derived) works because the app IS the writing.
- **One family with Display variant** (like Inter + Inter Display) is the modern best practice for UI-only typography.

### Line Height
- **UI text** (labels, buttons, navigation): 1.2-1.3. Tight, compact, functional.
- **Body text / reading**: 1.4-1.5. Comfortable scanning.
- **Long-form prose**: 1.45-1.6. Optimized for sustained reading. iA Writer dynamically adjusts this.
- **Rule of thumb**: Line height should increase as line length increases.

### Content Width
- **45-75 characters per line**, with 66 as the ideal. This is nearly universal across all research.
- For a 16px body font, this translates to roughly 600-700px content width.
- For an 18px serif font (like Manuscript's editor), this is roughly 650-750px.

---

## 6. Patterns Specific to Writing/Editor Apps

### Sidebar Design
| App | Sidebar Width | Font Size | Item Height | Active State |
|---|---|---|---|---|
| Linear | ~220-240px | 13-14px | ~32px | Accent background, white text |
| Notion | 224px | 14px | ~30px | Blue text, subtle blue background |
| Ulysses | ~220px | 13px | ~28px | System accent (blue) |
| Scrivener | ~220-250px | 13px | ~24px | System highlight |
| **Manuscript** | **232px** | **12px** | **~32px** | **Accent bg, white text** |

### Content Area
| App | Prose Font | Prose Size | Line Height | Max Width |
|---|---|---|---|---|
| iA Writer | iA Writer Quattro/Duo | 18-21px (variable) | Dynamic (1.4-1.6) | 64-80 chars |
| Notion | System serif option | 16px | 1.5 | ~720px |
| Ulysses | System or custom | 17-18px | 1.5-1.6 | ~680px |
| **Manuscript** | **Literata (serif)** | **18px** | **1.45** | **Flexible** |

### Common Patterns Across All Writing Apps
1. **Sidebar is navigation, not content** — always compact, never competes with the editor.
2. **Editor area has generous margins** — minimum 48px horizontal padding, often more.
3. **Content width is capped** — even on ultrawide monitors, prose never fills the screen.
4. **Chrome fades, content stays** — toolbars, panels, and navigation use muted colors; the text area is brightest.
5. **Typography is the design** — in writing apps, font choice, size, and spacing ARE the visual identity. Decorative elements are nearly absent.
6. **Right panels are optional** — they slide in/out. The default state is sidebar + editor only.
7. **Status bar is minimal** — word count, reading time, maybe document status. Never attention-grabbing.

---

## 7. Key Takeaways for Evaluating Manuscript's Design System

### What Manuscript Already Does Well (Based on Benchmarks)
- Warm-tinted grays (matches Linear's direction, appropriate for a literary/writing app)
- Serif for prose, sans for UI (gold standard pattern)
- Single accent color approach (copper/amber — warm, literary, distinctive)
- Sidebar width of 232px (within the 220-240px industry norm)
- Editor prose at 18px with 1.45 line height (solid — within the 17-21px range of writing apps)
- 4px-based spacing scale (matches modern best practice)

### Areas to Evaluate Against Benchmarks
1. **Gray count**: Manuscript defines 6 surface tokens + 6 text tokens + 5 border tokens = 17 gray-adjacent tokens. Linear achieves its look with ~5 core neutral values. Consider whether all 17 are pulling their weight or if some can be consolidated.
2. **Font size scale**: Manuscript uses 11, 12, 14, 16, 20, 24, 32 (7 steps). This matches the recommended maximum of 7-8. However, the jump from 11px to 12px is only 9% — these may be perceptually too similar to justify as separate scale steps. Consider whether 11px and 12px serve distinct enough purposes.
3. **Accent usage breadth**: Benchmark apps use accent on <10% of UI surface. Check if copper accent is used only for active states and CTAs, or if it has crept into backgrounds and decorative elements.
4. **Font weight range**: Manuscript uses 400, 500, 600, 700 (four weights). Best practice is three. Evaluate whether medium (500) and semibold (600) are perceptually distinct enough to warrant both, or if one could be dropped.
5. **Editor content width**: Manuscript says "flexible" — benchmark apps cap at 66-80 characters. If there is no max-width on the editor content area, sustained reading may suffer on wide screens.
6. **Section label pattern**: Linear uses 12px/600/uppercase for section labels. Manuscript uses 10px/uppercase. The 10px is unusually small — even on high-DPI displays this may be at the threshold of comfortable legibility.

---

## Sources

### Linear
- [How we redesigned the Linear UI (part II)](https://linear.app/now/how-we-redesigned-the-linear-ui)
- [A calmer interface for a product in motion](https://linear.app/now/behind-the-latest-design-refresh)
- [Linear Brand Color Palette](https://mobbin.com/colors/brand/linear)
- [The rise of Linear style design](https://medium.com/design-bootcamp/the-rise-of-linear-style-design-origins-trends-and-techniques-4fd96aab7646)
- [Linear design: The SaaS trend](https://blog.logrocket.com/ux-design/linear-design/)
- [Brand Guidelines - Linear](https://linear.app/brand)

### iA Writer
- [Responsive Typography: The Basics](https://ia.net/topics/responsive-typography-the-basics)
- [A Typographic Christmas (iA Writer fonts)](https://ia.net/topics/a-typographic-christmas)
- [From Monospace to Duospace](https://ia.net/topics/in-search-of-the-perfect-writing-font)
- [iA Writer's Typography and Color Scheme](https://data-enhanced.com/2011/06/29/ia-writers-typography-and-color-scheme/)
- [iA Writer 3.1 comes in colors](https://ia.net/topics/ia-writer-3-1-comes-in-colors)

### Notion
- [Notion Colors: All Hex Codes](https://matthiasfrank.de/en/notion-colors/)
- [UI Breakdown of Notion's Sidebar](https://medium.com/@quickmasum/ui-breakdown-of-notions-sidebar-2121364ec78d)
- [Notion Color Code Hex](https://www.notionavenue.co/post/notion-color-code-hex-palette)

### Color Palette Best Practices
- [How to Pick Grays in Your Design System](https://medium.com/cortes-studio/a-systematic-approach-to-harmonious-neutral-color-palettes-in-product-design-9b4aa19e2156)
- [Color in Design Systems (Nathan Curtis)](https://medium.com/eightshapes-llc/color-in-design-systems-a1c80f65fa3)
- [A Guide to Colors in Design Systems](https://supercharge.design/blog/a-guide-to-colors-in-design-systems)
- [11 Shades of Gray: A Color System Story](https://onesignal.com/blog/11-shades-of-gray-a-color-system-story/)

### Typography Scale
- [Defining a Modular Type Scale for Web UI](https://blog.prototypr.io/defining-a-modular-type-scale-for-web-ui-51acd5df31aa)
- [Typographic Hierarchy Made Easy](https://medium.com/design-bootcamp/typographic-hierarchy-made-easy-understanding-type-scales-in-ui-24a694f1e0e8)
- [Establishing a Type Scale](https://cieden.com/book/sub-atomic/typography/establishing-a-type-scale)
- [Typographic Scales - Design+Code](https://designcode.io/typographic-scales/)

### Spacing & Layout
- [Spacing, Grids, and Layouts](https://www.designsystems.com/space-grids-and-layouts/)
- [The 8pt Grid](https://blog.prototypr.io/the-8pt-grid-consistent-spacing-in-ui-design-with-sketch-577e4f0fd520)
- [Optimal Line Length for Readability](https://www.uxpin.com/studio/blog/optimal-line-length-for-readability/)
- [Line Spacing Best Practices](https://www.justinmind.com/blog/best-ux-practices-for-line-spacing/)

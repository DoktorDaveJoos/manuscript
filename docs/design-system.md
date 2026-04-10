# Manuscript Design System

> Source of truth for all UI work. Derived from the Pencil "Editor" screens,
> validated against the actual codebase, and benchmarked against Linear, iA Writer, and Notion.
>
> See also: `docs/research/design-system-benchmarks.md` for the full research.

---

## Design Principles

1. **Content is the hero** — The editor area is the brightest, most spacious region. UI chrome is muted and recedes. Typography IS the design.
2. **Clean & literary** — Neutral surfaces (white chrome, #fafafa content), Playfair Display display headings. Evokes a refined manuscript, not a code editor.
3. **Restraint over decoration** — One accent color. Three text tiers. No gradients, no shadows on content. Complexity lives in the writing, not the UI.
4. **8pt grid rhythm** — All spacing derives from an 8px base: 4, 8, 12, 16, 24, 32, 48, 64. This creates subconscious visual alignment and harmony.
5. **Breathing room** — Generous page margins (48px), outer spacing >= 2x inner spacing. White space signals quality and gives content room to breathe.

---

## Color Tokens

Defined in `resources/css/app.css` via `@theme` (light) and `html.dark` (dark override).

**Rule: Never use hardcoded hex values in components.** Every color must reference a token class.

### Surfaces (5 tokens)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `surface` | `#fafafa` | `#161616` | `bg-surface` | Content area background |
| `surface-card` | `#ffffff` | `#1c1c1c` | `bg-surface-card` | Cards, panels, menus, overlays |
| `surface-sidebar` | `#ffffff` | = surface-card | `bg-surface-sidebar` | Sidebar background |
| `neutral-bg` | `#f5f5f5` | `#242424` | `bg-neutral-bg` | Button fills, hover states |
| `surface-warm` | `#F5EDE3` | `#2A2620` | `bg-surface-warm` | Accent-tinted backgrounds, selected highlights |

### Text (4 active tiers + 2 specialty)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `ink` | `#141414` | `#E8E5DF` | `text-ink` | Primary: headings, body text, active labels |
| `ink-muted` | `#737373` | `#9A958E` | `text-ink-muted` | Secondary: metadata, timestamps, inactive nav, button text |
| `ink-faint` | `#A3A3A3` | `#6B665E` | `text-ink-faint` | Tertiary: placeholders, disabled text, hints |
| `ink-soft` | `#595959` | `#C8C3BA` | `text-ink-soft` | Body text in secondary panels (between ink and ink-muted) |
| `ink-warm` | `#6B6358` | `#6B6560` | `text-ink-warm` | Specialty: warm-tinted secondary text in accent contexts |
| `ink-whisper` | `#1414144D` | `#E8E5DF4D` | `text-ink-whisper` | Specialty: very faint overlays (30% opacity) |

**Choose by hierarchy, not feel:**
- Heading or label the user MUST read → `text-ink`
- Supporting info they'll scan → `text-ink-muted`
- Info that's there if they look → `text-ink-faint`
- When in doubt between `ink-soft` and `ink-muted`, use `ink-muted` (it's the standard secondary tier)

### Borders (3 active + 2 specialty)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `border` | `#e5e5e5` | `#2e2e2e` | `border-border` | Default: dividers, input borders, card outlines |
| `border-light` | `#ebebeb` | `#2a2a2a` | `border-border-light` | Panel header borders, lighter dividers |
| `border-subtle` | `#f0f0f0` | `#242424` | `border-border-subtle` | Between sections within a panel, inner dividers |
| `border-dashed` | `#d4d4d4` | `#3d3d3d` | `border-border-dashed` | Dashed: drop zones, placeholder outlines |
| `section-header` | `#d4d4d4` | `#3d3d3d` | `bg-section-header` | Section divider lines in sidebar |

### Accent (3 tokens — use sparingly)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `accent` | `#B87333` | `#D4956A` | `bg-accent` / `text-accent` | Primary CTA, links, focus rings, status indicators |
| `accent-dark` | `#9A6229` | `#B87333` | `hover:bg-accent-dark` | Hover state for accent buttons |
| `accent-light` | `#F5EDE3` | `#2A2620` | `bg-accent-light` | Accent-tinted badge backgrounds, selected row highlights |

> **Accent restraint:** The copper accent should appear on <10% of UI surface area (benchmarked against Linear). If accent is everywhere, nothing stands out. Use it only for: primary buttons, focus rings, links, and status indicators. Active sidebar items use `bg-ink` (dark) instead.

### Semantic Colors

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `delete` | `#DC2828` | `#D34545` | `bg-delete` / `text-delete` | Destructive actions, error text |
| `delete-bg` | `#FDF2F0` | `#3A2520` | `bg-delete-bg` | Error/danger background tint |
| `status-final` | `#6DBB7B` | `#6DBB7B` | `bg-status-final` | Chapter status: final |
| `status-revised` | `#8AB0C8` | `#D4956A` | `bg-status-revised` | Chapter status: revised |
| `status-draft` | `#B87333` | `#6B665E` | `bg-status-draft` | Chapter status: draft |
| `ai-green` | `#6DBB7B` | `#6DBB7B` | `text-ai-green` | AI assistant indicator |
| `drop` | `#4A7C59` | `#5A8F5C` | `text-drop` / `bg-drop` | Drag-and-drop target indicator |

### Plot Point Colors

| Token | Bg | Text |
|---|---|---|
| `plot-setup` | `bg-plot-setup-bg` | `text-plot-setup-text` |
| `plot-conflict` | `bg-plot-conflict-bg` | `text-plot-conflict-text` |
| `plot-turning` | `bg-plot-turning-bg` | `text-plot-turning-text` |
| `plot-resolution` | `bg-plot-resolution-bg` | `text-plot-resolution-text` |
| `plot-worldbuilding` | `bg-plot-worldbuilding-bg` | `text-plot-worldbuilding-text` |

**Always use these tokens for plot point colors** — never hardcode hex values. The tokens have dark mode variants.

### Act Colors (5 rotating sets)

Used for act header backgrounds, top borders, label text, and progress bar tracks. Consumed via `ACT_COLORS` in `lib/plot-constants.ts` as CSS `var()` references.

| Set | Bg | Border | Label | Track |
|---|---|---|---|---|
| Act 1 | `--color-act-1-bg` | `--color-act-1-border` | `--color-act-1-label` | `--color-act-1-track` |
| Act 2 | `--color-act-2-bg` | `--color-act-2-border` | `--color-act-2-label` | `--color-act-2-track` |
| Act 3 | `--color-act-3-bg` | `--color-act-3-border` | `--color-act-3-label` | `--color-act-3-track` |
| Act 4 | `--color-act-4-bg` | `--color-act-4-border` | `--color-act-4-label` | `--color-act-4-track` |
| Act 5 | `--color-act-5-bg` | `--color-act-5-border` | `--color-act-5-label` | `--color-act-5-track` |

Acts cycle through these 5 sets (`index % 5`). Both light and dark variants are defined in `app.css`.

### Color Psychology

- White (#ffffff) for UI chrome = clarity, openness, trust
- Neutral #fafafa for content = calm, focused reading environment
- Warm copper accent = craftsmanship, authenticity, warmth — used on <10% of surface
- The accent warmth against neutral whites creates a "premium handcrafted" feel

### Mathematical Spacing (8pt Grid)

- Base unit: 4px (micro adjustments), 8px (standard unit)
- All spacing should be multiples: 4, 8, 12, 16, 24, 32, 48, 64
- Standard content area padding: `px-12 py-10` (48px/40px)
- Rule: outer spacing >= 2x inner spacing for clear visual grouping

---

## Typography

### Font Families

| Stack | Class | Usage | Rule |
|---|---|---|---|
| Playfair Display, ui-serif | `font-serif` | Display headings (H1), dialog titles | **Only for Display and Dialog headings** — never for body text, section labels, or UI elements |
| Geist, ui-sans-serif, system-ui | `font-sans` (default) | All UI: buttons, labels, nav, panels, metadata, H2+, subheadings | Default — no class needed |

Note: Literata is loaded separately for the editor prose area (user-selectable via settings).

### Font Size Scale (8 steps)

Benchmarked against Linear (13px body) and iA Writer (constrained scale).
The scale follows a ~1.15–1.2 ratio, which suits dense editor UIs.

| Step | Size | Tailwind | Usage |
|---|---|---|---|
| 2xs | 11px | `text-[11px]` | Badge text, sidebar metadata, smallest labels |
| xs | 12px | `text-xs` | Sidebar items, small buttons, captions |
| sm | 13px | `text-[13px]` | Default button text, compact body text, form labels |
| base | 14px | `text-sm` | Default body text, input text, standard UI text |
| md | 16px | `text-base` | Panel titles, emphasized body, subheadings |
| lg | 20px | `text-xl` | Page headings |
| xl | 24px | `text-2xl` | Large headings, display |
| 2xl | 32px | `text-[32px]` | Editor chapter heading (h1) |

**Rules:**
- **Section labels** (sidebar headers, panel section dividers): `text-[11px] uppercase font-medium tracking-wide text-ink-muted`
- **No font sizes outside this scale** in new code. If you need 10px, use 11px. If you need 15px, use 14px or 16px. If you need 18px, that belongs in `.editor-prose` only.
- 13px (`text-[13px]`) is the standard "compact" size — use for buttons, toolbar text, sidebar nav items. 14px (`text-sm`) is the standard "comfortable" size — use for body text and form inputs.

### Standard Heading Sizes

| Level | Classes | When |
|---|---|---|
| Display | `font-serif text-[32px] leading-10 font-semibold tracking-[-0.01em] text-ink` | Hero headlines, empty states, onboarding |
| H1 Page | `text-xl font-semibold tracking-[-0.01em] text-ink` | Page titles inside the app chrome |
| H2 Dialog | `font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink` | Dialog/modal titles |
| H2 Section | `text-base font-semibold text-ink` | Section headers within a page |
| H3 Card | `text-sm font-medium text-ink` | Card titles, list items |

Rule: `font-serif` (Playfair Display) ONLY for Display and Dialog headings — always `font-semibold`. `font-bold`/`font-extrabold` never used for UI headings — max is `font-semibold`.

### Font Weights (3 tiers)

| Weight | Class | Usage |
|---|---|---|
| 400 (regular) | `font-normal` | Body text, descriptions, input values |
| 500 (medium) | `font-medium` | Buttons, section labels, active nav items, panel headers |
| 600 (semibold) | `font-semibold` | Headings (h1–h3), dialog titles, emphasis |

> **Bold (700)**: Reserved for `<strong>` in prose content only. Never use `font-bold` for UI elements.

### Editor Prose

The editor uses `.editor-prose` (defined in `app.css`):
- Font: Literata (`font-serif`)
- Size: 18px (customizable via `--editor-font-size`)
- Line-height: 1.45
- Alignment: justified with auto-hyphens
- Paragraphs: `margin-bottom: 0.6em` (vertical spacing, no indentation)
- **Max width**: Editor content should be capped at ~65–75 characters per line for readability

---

## Spacing

Uses Tailwind's default 4px-based spacing scale.

| Token | Value | Tailwind | Common use |
|---|---|---|---|
| 2xs | 2px | `0.5` | Micro gaps (icon-to-text within a badge) |
| xs | 4px | `1` | Tight gaps (between list items, inline spacing) |
| sm | 8px | `2` | Default inner padding (sidebar items, compact controls) |
| md | 12px | `3` | Sidebar horizontal padding, button internal padding |
| lg | 16px | `4` | Panel section padding, between form fields |
| xl | 24px | `6` | Between sections, generous panel padding |
| 2xl | 32px | `8` | Major section separation |
| 3xl | 48px | `12` | Editor horizontal margins, dialog padding |

### Contextual Rules

| Context | Spacing |
|---|---|
| Sidebar internal padding | `px-3` (12px) horizontal |
| Sidebar item vertical padding | `py-1.5` (6px) |
| Panel section padding | `p-4` (16px) |
| Editor content side margins | `px-12` (48px) minimum |
| Between form fields | `gap-4` (16px) |
| Between major sections | `gap-6` (24px) or border separator |
| Dialog form padding | `p-10` (40px) |
| Compact lists (chapters) | `gap-0.5` to `gap-1` |

---

## Border Radius (5 steps)

| Step | Value | Tailwind | Usage |
|---|---|---|---|
| xs | 4px | `rounded` | Progress bars, inline tags, micro elements |
| sm | 6px | `rounded-md` | Buttons, inputs, selects, menu items, sidebar items |
| md | 8px | `rounded-lg` | Panels, dropdowns, popovers |
| lg | 12px | `rounded-xl` | Cards, dialogs, command palette |
| full | 9999px | `rounded-full` | Pills, badges, avatars, toggles |

**Rules:**
- Buttons and inputs always use `rounded-md`
- Cards always use `rounded-xl`
- Panels and dropdowns always use `rounded-lg`
- Dialogs always use `rounded-xl`
- **No arbitrary radius values** (`rounded-[5px]`, `rounded-[10px]`, `rounded-[14px]`). Map to the nearest step.

---

## Icon Sizes (5 steps)

All icons use **Lucide React** (`lucide-react`). Default stroke width is 2; use 1.5 for icons at 20px+.

| Step | Size | Tailwind | Usage |
|---|---|---|---|
| 2xs | 12px | `size-3` | Micro indicators, decorative inline icons |
| xs | 14px | `size-3.5` | Compact UI: sidebar items, small buttons, inline actions |
| sm | 16px | `size-4` | Standard UI: toolbar buttons, menu items, nav icons |
| md | 20px | `size-5` | Medium: panel action buttons, prominent controls |
| lg | 24px | `size-6` | Large: panel headers, empty state illustrations |

> **14px is the standard compact icon size** (sidebar, chapter list, small controls). 16px is the standard comfortable icon size (toolbars, menus).

---

## Layout

### Editor Layout (Primary Screen)

```
┌─────────────────────────────────────────────────────────┐
│ TopBar (h-12 / 48px)                                    │
├──────────┬──────────────────────────┬───────────────────┤
│ Sidebar  │ Editor Content           │ Right Panel       │
│ w-[232px]│ flex-1                   │ w-[272px]         │
│          │ max-w-prose centered     │ or w-[320px]      │
│          │                          │ (slides in/out)   │
├──────────┴──────────────────────────┴───────────────────┤
│ Status Bar (h-[42px])                                   │
└─────────────────────────────────────────────────────────┘
```

### Fixed Dimensions

| Element | Size | Notes |
|---|---|---|
| Sidebar | 232px wide | All screens, non-negotiable |
| Right panel (notes/metrics) | 272px wide | Chapter notes, craft metrics |
| Right panel (AI/detail) | 320px wide | AI chat, plot detail |
| TopBar | 48px tall (`h-12`) | App navigation |
| Status bar | 42px tall | Word count, metadata |
| Sidebar item | ~32px tall | Navigation items |
| Button (default) | ~38px tall | `py-2 text-[13px]` |
| Button (sm) | ~32px tall | `py-1.5 text-xs` |
| Input field | ~44px tall | Including border |

---

## Components

### shadcn First

Always try to solve a UI problem with a shadcn component before building custom markup. Use `npx shadcn@latest search` to check registries. If a shadcn component exists for the pattern, install and adapt it to the project's design tokens.

### Reuse First

Always check `resources/js/components/ui/` before creating new elements:

| Component | Purpose | Key props |
|---|---|---|
| `Button` | All buttons | `variant: primary\|secondary\|ghost\|danger\|accent`, `size: sm\|default\|lg` |
| `Input` | Text inputs | `variant: default\|dialog` |
| `Select` | Dropdowns | `variant: default\|dialog` |
| `Textarea` | Multi-line input | `variant: default\|dialog` |
| `Dialog` | Modals | `onClose`, `width?`, `backdrop: none\|light\|dark` |
| `Drawer` | Side panels | `onClose`, slides from right |
| `PanelHeader` | Panel headers | `title`, `onClose?`, `suffix?` |
| `SectionLabel` | Uppercase labels | `as: span\|label`, `className?` |
| `ContextMenu` | Right-click menus | `.Item`, `.Submenu`, `.Separator` |
| `FormField` | Label + input + error | `label`, `error?`, `children` |
| `Card` | Content containers | `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter` |
| `ToggleGroup` | Option selection (2–7 choices) | `type: single\|multiple`, `ToggleGroupItem` |
| `Toggle` / `ToggleRow` | Boolean settings | `checked`, `onChange` |
| `Checkbox` | Multi-select | `checked`, `onChange` |
| `Collapsible` | Expandable sections | Animated open/close |
| `Kbd` | Keyboard shortcuts | Renders styled key cap |

### Button Variants

| Variant | Style | Usage |
|---|---|---|
| `primary` | `bg-ink text-surface` | Primary actions (Save, Create) |
| `secondary` | `border-border text-ink-muted` | Secondary actions (Cancel) |
| `ghost` | `text-ink-muted` (no bg/border) | Toolbar, minimal actions |
| `danger` | `bg-delete text-surface` | Destructive (Delete) |
| `accent` | `bg-accent text-surface` | Brand CTA (Upgrade, AI features) |

### Card

All content containers use the `Card` component from `@/components/ui/Card`.

**Rules:**
- **Always has a border** — `border border-border-light` is baked into the base component
- **Always `rounded-xl`** — no exceptions
- **Default padding**: `p-6` (24px) via `CardContent`. Use `className="p-4"` for compact cards
- For simple cards (stat cards), use `<Card className="p-6">` without sub-components
- For structured cards, use the full anatomy: `CardHeader` → `CardTitle` / `CardDescription` → `CardContent` → `CardFooter`
- For interactive cards (clickable), add `cursor-pointer hover:shadow-md transition-shadow` via `className`
- No shadows by default — the border provides sufficient separation

### ToggleGroup

Use `ToggleGroup` + `ToggleGroupItem` for option selection with 2–7 mutually exclusive choices (format pickers, font selectors, theme selectors). Never loop `Button` with manual active state.

**Active state:** `bg-ink text-surface font-semibold` (dark fill — high contrast, unambiguous)
**Inactive state:** `bg-neutral-bg text-ink-muted` (subtle, recedes)

### Sidebar Navigation

- **Active item**: `bg-ink text-surface font-medium rounded-md` — dark inverted background for clear focus
- **Default item**: `text-ink-muted hover:text-ink hover:bg-neutral-bg`
- **Section labels**: `text-[11px] uppercase font-medium tracking-wide text-ink-muted`
- **Chapter abbreviation**: First two letters of storyline + chapter number (e.g., "Ma1", "Ba2")

---

## Dark Mode

Toggled via `html.dark` class. All tokens have dark variants in `app.css`.

### Rules

1. **Never use `bg-white` alone** — always pair: `bg-white dark:bg-surface-card` or use `bg-surface-sidebar`
2. **Surfaces are neutral dark** (#161616, #1c1c1c) — never pure black (#000000)
3. **Accent shifts lighter** (#B87333 → #D4956A) for contrast on dark backgrounds
4. **Borders get darker** (#e5e5e5 → #2e2e2e) in dark mode
5. **Book preview pages are exempt** — they use hardcoded colors to represent printed output

---

## Animations

| Animation | Duration | Easing | Usage |
|---|---|---|---|
| `collapsible-down` | 200ms | ease-out | Expanding sections |
| `collapsible-up` | 200ms | ease-out | Collapsing sections |
| `slide-down` | 150ms | ease-out | Dropdown/popover entry |
| `slideInRight` | 200ms | ease-out | Side panel entry |

**Default transitions:** `transition-colors` (150ms) for color changes, `transition-opacity` for fades.

---

## Naming Conventions

- **Color tokens**: `--color-{semantic-name}` — semantic, not visual (`ink` not `dark-gray`, `accent` not `copper`)
- **Components**: PascalCase (`Button`, `PanelHeader`)
- **Variants**: via props (`variant="primary"`, `size="sm"`)
- **Class merging**: `cn()` utility from `@/lib/utils`
- **Variant definitions**: CVA (`class-variance-authority`) for multi-variant components

---

## Checklist for New UI

Before submitting any UI change:

- [ ] **No hardcoded hex colors** in `.tsx` files (all colors via Tailwind token classes)
- [ ] **Font sizes from the scale**: 11, 12, 13, 14, 16, 20, 24, or 32px only
- [ ] **Icon sizes from the scale**: 12, 14, 16, 20, or 24px only
- [ ] **Border radius from the scale**: `rounded`, `rounded-md`, `rounded-lg`, `rounded-xl`, or `rounded-full` only
- [ ] **Typography**: `font-sans` for UI, `font-serif` only for Display and Dialog headings
- [ ] **Font weights**: 400/500/600 only (700 only inside prose `<strong>`)
- [ ] **Dark mode works**: uses tokens, no bare `bg-white` or hardcoded colors
- [ ] **Reuses UI components**: no ad-hoc `<button>`, `<input>`, or modal markup
- [ ] **Layout widths**: sidebar 232px, right panels 272/320px
- [ ] **Accent restraint**: accent only on active states, CTAs, and focus rings

---

## Known Debt (cleanup backlog)

These issues exist in the current codebase and should be fixed incrementally:

### Hardcoded hex colors (~95 occurrences)
Worst offenders: `AiPreparation.tsx` (22 values), `PlotPointSection.tsx` (15), `PlotWizardModal.tsx` (8).
Plot components should use the `plot-*-bg` / `plot-*-text` tokens.

### Bare `bg-white` without dark mode (~11 occurrences)
Should be `bg-surface-sidebar` or `bg-white dark:bg-surface-card`.

### Non-standard icon sizes (~128 occurrences)
`size={14}` is used correctly (it's in our scale). But `size={13}`, `size={10}`, `size={18}`, `size={15}` should be mapped to the nearest scale step.

### Arbitrary border radius (~81 occurrences)
`rounded-[5px]` → `rounded-md`, `rounded-[10px]` → `rounded-lg`, `rounded-[12px]` → `rounded-xl`, `rounded-[14px]` → `rounded-xl`.

### `text-red-500` usage
One occurrence in `settings/appearance.tsx` — should be `text-delete`.

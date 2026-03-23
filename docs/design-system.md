# Manuscript Design System

> Source of truth for all UI work. Derived from the Pencil "Editor" screens,
> validated against the actual codebase, and benchmarked against Linear, iA Writer, and Notion.
>
> See also: `docs/research/design-system-benchmarks.md` for the full research.

---

## Design Principles

1. **Content is the hero** — The editor area is the brightest, most spacious region. UI chrome is muted and recedes. Typography IS the design.
2. **Warm & literary** — Warm-tinted grays (not cool/neutral), cream surfaces, serif prose. Evokes a physical manuscript, not a code editor.
3. **Restraint over decoration** — One accent color. Three text tiers. No gradients, no shadows on content. Complexity lives in the writing, not the UI.
4. **Compact chrome, generous prose** — Sidebars and panels are dense. The editor breathes with wide margins and comfortable line height.
5. **Dark mode preserves warmth** — Dark surfaces are warm (#161514), never pure black. The copper accent shifts lighter for contrast.

---

## Color Tokens

Defined in `resources/css/app.css` via `@theme` (light) and `html.dark` (dark override).

**Rule: Never use hardcoded hex values in components.** Every color must reference a token class.

### Surfaces (5 tokens)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `surface` | `#FCFAF7` | `#161514` | `bg-surface` | Page background, main content area |
| `surface-card` | `#FCFAF7` | `#1C1B1A` | `bg-surface-card` | Elevated cards, panels (diverges from surface in dark mode) |
| `surface-sidebar` | `#FFFFFF` | = surface-card | `bg-surface-sidebar` | Sidebar background — pure white in light, card-tone in dark |
| `neutral-bg` | `#F1EEEA` | `#242322` | `bg-neutral-bg` | Button fills, hover states, input backgrounds, code blocks |
| `surface-warm` | `#F5EDE3` | `#2A2620` | `bg-surface-warm` | Accent-tinted backgrounds, selected highlights |

> **Note:** `surface` and `surface-card` are identical in light mode but diverge in dark mode. This is intentional — keep them semantically distinct.

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
| `border` | `#E4E2DD` | `#2E2D2B` | `border-border` | Default: dividers, input borders, card outlines |
| `border-subtle` | `#EEECE8` | `#242220` | `border-border-subtle` | Lighter: between sections within a panel, inner dividers |
| `border-dashed` | `#D5D2CC` | `#3D3A35` | `border-border-dashed` | Dashed: drop zones, placeholder outlines |
| `border-light` | `#E4E2DD` | `#2A2722` | `border-border-light` | Same as `border` in light; slightly warmer in dark. Use for panel header borders |
| `section-header` | `#D5D2CC` | `#3D3A35` | `bg-section-header` | Section divider lines in sidebar |

### Accent (3 tokens — use sparingly)

| Token | Light | Dark | Class | When to use |
|---|---|---|---|---|
| `accent` | `#B87333` | `#D4956A` | `bg-accent` / `text-accent` | Active nav item, primary CTA, links, focus rings |
| `accent-dark` | `#9A6229` | `#B87333` | `hover:bg-accent-dark` | Hover state for accent buttons |
| `accent-light` | `#F5EDE3` | `#2A2620` | `bg-accent-light` | Accent-tinted badge backgrounds, selected row highlights |

> **Accent restraint:** The copper accent should appear on <10% of UI surface area (benchmarked against Linear). If accent is everywhere, nothing stands out. Use it only for: active sidebar item, primary buttons, focus rings, links, and status indicators.

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

---

## Typography

### Font Families

| Stack | Class | Usage | Rule |
|---|---|---|---|
| Geist, ui-sans-serif, system-ui | `font-sans` (default) | All UI: buttons, labels, nav, panels, metadata | Default — no class needed |
| Literata, ui-serif | `font-serif` | Editor prose only | **Only inside `.editor-prose`** — never for UI elements |

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
- Paragraphs: `text-indent: 1em` (flush after headings/breaks)
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
| md | 8px | `rounded-lg` | Cards, panels, dropdowns, popovers |
| lg | 12px | `rounded-xl` | Dialogs, large cards, command palette |
| full | 9999px | `rounded-full` | Pills, badges, avatars, toggles |

**Rules:**
- Buttons and inputs always use `rounded-md`
- Cards and panels always use `rounded-lg`
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

### Sidebar Navigation

- **Active item**: `bg-accent text-surface rounded-md` — the ONE place accent is a background
- **Default item**: `text-ink-muted hover:text-ink hover:bg-neutral-bg`
- **Section labels**: `text-[11px] uppercase font-medium tracking-wide text-ink-muted`
- **Chapter abbreviation**: First two letters of storyline + chapter number (e.g., "Ma1", "Ba2")

---

## Dark Mode

Toggled via `html.dark` class. All tokens have dark variants in `app.css`.

### Rules

1. **Never use `bg-white` alone** — always pair: `bg-white dark:bg-surface-card` or use `bg-surface-sidebar`
2. **Surfaces are warm dark** (#161514, #1C1B1A) — never pure black (#000000)
3. **Accent shifts lighter** (#B87333 → #D4956A) for contrast on dark backgrounds
4. **Borders get darker** (#E4E2DD → #2E2D2B) but stay warm-tinted
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
- [ ] **Typography**: `font-sans` for UI, `font-serif` only in `.editor-prose`
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

# Design System (cheatsheet)

> Always-loaded summary. Full reference: `docs/design-system.md`. Tokens defined in `resources/css/app.css`.

## Hard rules

- **Reuse first, shadcn second, custom last.** Always check `resources/js/components/ui/` for an existing component before writing markup. If nothing fits, install a shadcn component and adapt it to our tokens. Custom markup is a last resort — and the moment a custom snippet appears in a second file, lift it into `components/ui/`. A duplicated button/input/chat surface IS a bug.
- No hardcoded hex in `.tsx` — only Tailwind token classes.
- Font sizes from the 8-step scale below — no `text-[10px]`, `text-[15px]`, `text-[18px]`, `text-[22px]`, `text-[26px]`.
- Icon sizes from the 5-step scale — no `size={10}`, `size={13}`, `size={15}`, `size={18}`.
- Border radius from the 5-step scale — no `rounded-[5px]`, `rounded-[10px]`, `rounded-[14px]`.
- `font-serif` only on Display headings, Dialog titles, and inside `.editor-prose`. UI defaults to `font-sans`.
- Font weights `font-normal` / `font-medium` / `font-semibold` only. `font-bold` reserved for `<strong>` in prose.
- No bare `bg-white` — use `bg-surface-sidebar`, or pair `bg-white dark:bg-surface-card`.
- Reuse `resources/js/components/ui/` before reaching for raw `<button>`, `<input>`, `<form>`, `<label>`, `<dialog>`.
- AI chat compose surfaces (textarea + send button for talking to an agent) use `<AiChatInput>` — never assemble your own.

## Color tokens

Names only — hex pairs and dark-mode mappings live in `app.css`; full table in `docs/design-system.md`.

- **Surfaces**: `surface`, `surface-card`, `surface-sidebar`, `neutral-bg`, `surface-warm`
- **Text**: `ink` (primary) · `ink-muted` (secondary) · `ink-faint` (tertiary) · `ink-soft` (panel body) · `ink-warm` · `ink-whisper`
- **Borders**: `border`, `border-light`, `border-subtle`, `border-dashed`, `section-header`
- **Accent** (≤10% of UI surface): `accent`, `accent-dark` (hover), `accent-light`
- **Semantic**: `delete`, `delete-bg`, `status-final`, `status-revised`, `status-draft`, `ai-green`, `drop`
- **Plot / Act tracks**: `plot-{setup|conflict|turning|resolution|worldbuilding}-{bg|text}`, `act-{1-5}-{bg|border|label|track}` — see `docs/design-system.md`.

Pick text by hierarchy, not feel: must read → `text-ink`; supporting → `text-ink-muted`; if they look → `text-ink-faint`; secondary panel body → `text-ink-soft`.

## Type scale (8 steps)

- 11px → `text-[11px]` — badges, smallest labels
- 12px → `text-xs` — sidebar items, small buttons
- 13px → `text-[13px]` — default button text, compact body
- 14px → `text-sm` — body, inputs, H3 card titles
- 16px → `text-base` — panel titles, H2 section
- 20px → `text-xl` — H1 page
- 24px → `text-2xl` — H2 dialog
- 32px → `text-[32px]` — Display / chapter h1 (and `.editor-prose` only beyond)

## Icon scale (5 steps)

`size-3` (12) · `size-3.5` (14) · `size-4` (16) · `size-5` (20) · `size-6` (24). 14px is standard compact (sidebar, small buttons); 16px is standard comfortable (toolbars, menus).

## Radius scale (5 steps)

`rounded` (4 — progress bars, micro tags) · `rounded-md` (6 — buttons, inputs, menu items) · `rounded-lg` (8 — panels, popovers) · `rounded-xl` (12 — cards, dialogs, command palette) · `rounded-full` (pills, badges, avatars).

## Heading recipes

- **Display**: `font-serif text-[32px] leading-10 font-semibold tracking-[-0.01em] text-ink`
- **H1 page**: `text-xl font-semibold tracking-[-0.01em] text-ink`
- **H2 dialog**: `font-serif text-2xl leading-8 font-semibold tracking-[-0.01em] text-ink`
- **H2 section**: `text-base font-semibold text-ink`
- **H3 card / row title**: `text-sm font-medium text-ink` — never `text-[14px]`
- **SectionLabel**: `text-[11px] uppercase font-medium tracking-wide text-ink-muted` (use `<SectionLabel>`)
- **PanelHeader title**: `text-[11px] font-semibold tracking-[0.06em] text-ink uppercase` (use `<PanelHeader>`)

## Layout

- **Sidebar** 232px (collapses to 48px). **Right panels**: 272px (notes / AI) or 320px (chat).
- **AccessBar** `w-12` · **EditorBar** `h-[38px]` · **PanelHeader** `h-11` · **Status bar** 42px.
- **Editor prose** content: `w-full max-w-[660px] px-[30px]`.
- **Form-style pages** (Settings, Publish, similar): `mx-auto w-full max-w-[760px] px-12 pt-12 pb-[80vh]`, `gap-9` between top-level sections, `<SectionLabel variant="section">` + `<Card>` per section, `p-6` card padding, `px-6 py-3.5` for toggle / control rows.
- **Preview / canvas pages** (Export-like): full-width split, no 760px chrome.

## Components

Always check `resources/js/components/ui/` before building markup: `Button` · `Input` · `Select` · `Textarea` · `SearchInput` (filter/search bar) · `AiChatInput` (AI chat compose surface) · `Dialog` · `Drawer` · `PanelHeader` · `SectionLabel` · `ContextMenu` · `FormField` · `Card` (`rounded-xl`, `border border-border-light`, default `p-6`) · `Toggle` · `ToggleRow` · `ToggleGroup` · `Checkbox` · `Collapsible` · `Kbd` · `Alert` · `Badge` · `NavItem` · `PageHeader`.

Button variants: `primary` (`bg-ink text-surface`) · `secondary` (`border-border text-ink-muted`) · `ghost` · `danger` (`bg-delete`) · `accent` (`bg-accent`). Sizes: `sm` · `default` · `lg` · `icon`.

## Common drift — don't repeat

- Subsection titles drifting to `text-[14px] font-medium` → use `text-sm font-medium text-ink`.
- Toggle / control rows on `py-4` or `py-[18px]` → use `py-3.5`.
- Raw `<form>` / `<label>` blocks → wrap inputs with `FormField`.
- Raw `<button>` for actions → use `Button` (any variant).
- Custom textarea + send button for AI chat → use `<AiChatInput>`.
- Custom magnifier + input + clear button for search → use `<SearchInput>`.
- `bg-white` without dark variant → `bg-surface-sidebar` or pair with `dark:bg-surface-card`.
- `text-red-500` → `text-delete`.
- Arbitrary radius (`rounded-[5px]`, `rounded-[14px]`) → map to nearest scale step.
- Hardcoded plot colors → `bg-plot-*-bg` / `text-plot-*-text`.

---
name: design-system
description: Enforces the Manuscript design system when building or modifying UI components. Activates when creating pages, styling components, choosing colors, adding icons, building layouts, creating panels, or working with any visual element. Also triggers when user mentions design, style, color, layout, spacing, or "make it look like".
---

# Manuscript Design System Enforcement

**Full reference**: `docs/design-system.md`

Read that file if you haven't in this conversation. This skill is the enforcement summary.

## Hard Rules (never break)

1. **No hardcoded hex colors in `.tsx` files.** Every color must be a Tailwind token class (`text-ink`, `bg-surface`, `border-border`, etc.). The only exception is `app.css` where tokens are defined.

2. **Font sizes from the 8-step scale only:**
   - 11px (`text-[11px]`) — badges, smallest labels
   - 12px (`text-xs`) — sidebar items, small buttons
   - 13px (`text-[13px]`) — default button text, compact body
   - 14px (`text-sm`) — default body text, inputs
   - 16px (`text-base`) — panel titles, emphasis
   - 20px (`text-xl`) — page headings
   - 24px (`text-2xl`) — large headings
   - 32px (`text-[32px]`) — editor chapter heading
   - No 10px, 15px, 18px (outside `.editor-prose`), 22px, or 26px.

3. **Icon sizes from the 5-step scale only:**
   - 12px (`size-3`) — micro indicators
   - 14px (`size-3.5`) — compact UI (sidebar, small buttons)
   - 16px (`size-4`) — standard UI (toolbar, menus)
   - 20px (`size-5`) — medium (panel actions)
   - 24px (`size-6`) — large (panel headers)
   - No size={13}, size={10}, size={18}, size={15}.

4. **Border radius from the 5-step scale only:**
   - `rounded` (4px) — progress bars, micro elements
   - `rounded-md` (6px) — buttons, inputs, menu items
   - `rounded-lg` (8px) — cards, panels, dropdowns
   - `rounded-xl` (12px) — dialogs, large cards
   - `rounded-full` — pills, badges, avatars
   - No `rounded-[5px]`, `rounded-[10px]`, `rounded-[14px]`.

5. **`font-serif` only inside `.editor-prose`.** All UI text uses `font-sans` (the default).

6. **Font weights: 400/500/600 only.** 700 is reserved for `<strong>` in prose.

7. **No bare `bg-white`** — use `bg-surface-sidebar` or `bg-white dark:bg-surface-card`.

8. **Layout widths are fixed**: sidebar 232px, right panels 272px or 320px.

## Color Quick Reference

### Text hierarchy (choose by importance, not feel)
- Must read → `text-ink`
- Supporting → `text-ink-muted`
- If they look → `text-ink-faint`
- Secondary body → `text-ink-soft`

### Backgrounds
- Page: `bg-surface`
- Cards/panels: `bg-surface-card`
- Sidebar: `bg-surface-sidebar`
- Button fills/hovers: `bg-neutral-bg`
- Accent tint: `bg-accent-light` or `bg-surface-warm`

### Borders
- Default: `border-border`
- Subtle: `border-border-subtle`
- Dashed: `border-border-dashed`

### Accent (use sparingly — <10% of UI surface)
- `bg-accent` / `text-accent` — active items, CTAs
- `hover:bg-accent-dark` — accent hover state
- `bg-accent-light` — accent-tinted backgrounds

## Component Reuse

Always check `resources/js/components/ui/` first:
Button, Input, Select, Textarea, Dialog, Drawer, PanelHeader, SectionLabel, ContextMenu, FormField, Toggle, ToggleRow, Checkbox, Collapsible, Kbd

## Section Labels Pattern
```
text-[11px] uppercase font-medium tracking-wide text-ink-muted
```

## Before Completing UI Work

1. No hardcoded hex in component files
2. Dark mode works (tokens, not hardcoded colors)
3. Font sizes, icon sizes, radius all from the scale
4. Reuses existing components — no ad-hoc `<button>` or `<input>`

# Export Page Design System Alignment

**Date:** 2026-03-25
**Status:** Approved
**Scope:** Bring the Export page into sync with Manuscript's design system

## Context

The Export page was built with several custom patterns that diverge from the shared component library. This spec aligns it with the design system used across the rest of the app (editor panels, settings pages, plot panels).

## Changes

### 1. Reading Order Panel Header

**Problem:** Custom header uses 14px/semibold/text-ink with a subtitle. Every other panel uses `PanelHeader` (12px/medium/tracking-[0.08em]/text-ink-muted/uppercase).

**Solution:** Replace the custom header with the shared `PanelHeader` component.

```tsx
<PanelHeader
    title={t('readingOrder')}
    icon={<TableOfContents size={14} className="text-ink-faint" />}
    suffix={
        <button onClick={toggleCollapsed} className="...">
            <PanelLeftClose size={14} />
        </button>
    }
/>
```

- Use `icon` prop with `TableOfContents` from lucide-react (same pattern as GlobalFindDrawer's Search icon)
- Use `suffix` prop for the collapse button (not `onClose`, which renders an X)
- Drop the subtitle "Drag to reorder chapters" entirely — discoverable from drag handles
- **Note:** `PanelHeader` uses `border-b border-border` (stronger than the panel's `border-border-subtle`). This is intentional — it matches every other `PanelHeader` usage in the app (GlobalFindDrawer, PlotPointDetailPanel, etc.). The header border is meant to be more prominent than section dividers.

### 2. Collapsed Rail — Icon Instead of Vertical Text

**Problem:** Collapsed state shows vertical rotated text "READING ORDER" in a 36px rail. No other panel does this.

**Solution:** Replace vertical text with `TableOfContents` icon.

```tsx
// Collapsed state
<aside className="flex h-full w-9 shrink-0 flex-col items-center border-r border-border-subtle bg-white pt-3 dark:bg-surface-card">
    <button onClick={toggleCollapsed} className="...">
        <PanelLeftOpen size={14} />
    </button>
    <TableOfContents size={14} className="mt-3 text-ink-faint" />
</aside>
```

- Same `TableOfContents` icon as in the panel header — creates visual continuity
- Expand chevron above the icon to toggle open

### 3. Collapsible Sections — Radix Collapsible

**Problem:** Custom `CollapsibleSection` using CSS Grid animation (`grid-rows-[1fr]`/`grid-rows-[0fr]`). The app has a Radix `Collapsible` component in `components/ui/Collapsible.tsx` sitting unused.

**Solution:** Replace custom implementation with Radix Collapsible.

Each section (Front Matter, Chapters, Back Matter) becomes an independent `Collapsible`:

```tsx
<Collapsible defaultOpen>
    <div className="border-b border-border-subtle">
        <div className="px-4 pt-3 pb-2">
            <CollapsibleTrigger asChild>
                <SectionHeader count={orderedChapters.length}>
                    {t('chapters')}
                </SectionHeader>
            </CollapsibleTrigger>
        </div>
        <CollapsibleContent className="data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out] overflow-hidden">
            {/* section content */}
        </CollapsibleContent>
    </div>
</Collapsible>
```

- `defaultOpen` on all three sections (current behavior)
- Each section expands/collapses independently (not accordion)
- **Animation:** Use `collapsible-up` / `collapsible-down` keyframes already defined in `resources/css/app.css` (lines 121-145), matching the pattern in `EditorialReviewSection.tsx`
- **SectionHeader refactor:** Remove `expanded` and `onToggle` props. Chevron rotation driven by Radix `data-[state=open]` / `data-[state=closed]` attributes instead of React state. New signature: `SectionHeader({ children, count? })` — it becomes a pure display component wrapped by `CollapsibleTrigger asChild`
- Remove `expandedSections` state management and `CollapsibleSection` component entirely

### 4. Template Cards — Redesign

**Problem:** Current 140px-wide cards are cramped. "Aa Bb Cc" preview is generic. "Basic" pack badge adds no information (all templates are Basic).

**Solution:** Redesign template cards to feel like miniature book pages.

- Width: 160px, explicit height: `h-[200px]` (portrait book-page ratio, consistent across templates regardless of font rendering)
- Remove "Basic" pack badge
- Preview content: template name rendered as a chapter heading (heading font), followed by 2-3 lines of lorem ipsum in the body font at ~9px — mimicking actual export output
- Paper-like appearance: `bg-white` in both light and dark mode (these are book page previews — same rationale as the `#FFFEFA` paper color in ExportPreview). Subtle page shadow.
- Selection state unchanged: `border-accent ring-2 ring-accent`

```tsx
<button className={cn(
    'flex h-[200px] w-[160px] shrink-0 flex-col rounded-lg border p-4 text-left transition-all',
    'bg-white',
    'shadow-[0_2px_8px_#00000008] dark:shadow-[0_2px_8px_#00000020]',
    isSelected
        ? 'border-accent ring-2 ring-accent'
        : 'border-border-light hover:border-border-strong',
)}>
    {/* Chapter heading in heading font */}
    <span style={{ fontFamily: template.headingFont }}
        className="text-[13px] font-semibold text-ink leading-tight">
        {template.name}
    </span>

    {/* Lorem preview in body font */}
    <p style={{ fontFamily: template.bodyFont }}
        className="mt-2 text-[9px] leading-[1.6] text-ink-muted line-clamp-5">
        The morning sun cast long shadows across the garden path...
    </p>
</button>
```

**Note:** `TemplateSelector.tsx` container (`flex gap-2.5 overflow-x-auto pb-1`) may need a `pb` increase to accommodate the taller cards' box-shadow.

### 5. "More Templates Coming" Alert

**Problem:** No Alert component exists. Need to communicate that more templates are planned.

**Solution:** Create a shadcn-style `Alert` component and place it below the template selector.

**New file:** `resources/js/components/ui/Alert.tsx`

```tsx
// Exports: Alert, AlertTitle, AlertDescription
// Variants: default, info, destructive
// Pattern: matches shadcn Alert (div with role="alert", icon slot, title, description)
```

Variant styles (using only tokens that exist in the theme):
- `default`: `border-border bg-surface-card text-ink-soft`
- `info`: `border-accent/30 bg-accent/5 text-ink-soft` with `Info` icon from lucide-react
- `destructive`: `border-delete/30 bg-delete/5 text-ink-soft` with `AlertCircle` icon

**Note:** No `warning` variant — `--color-warning` does not exist in the theme. If needed later, add the token first.

Usage on export page:

```tsx
<Alert variant="info">
    <AlertTitle>{t('templateAlertTitle')}</AlertTitle>
    <AlertDescription>{t('templateAlertDescription')}</AlertDescription>
</Alert>
```

i18n keys to add in `resources/js/i18n/en/export.json`:
```json
{
    "templateAlertTitle": "More templates on the way",
    "templateAlertDescription": "We're working hard on new templates. Stay tuned for upcoming styles and layouts."
}
```

Placed below `TemplateSelector`, before the `CustomizePanel`.

### 6. Preview Header Tracking Fix

**Problem:** `tracking-[0.01em]` — doesn't match design system's `tracking-[0.08em]`.

**Solution:** Change to `tracking-[0.08em]` in `ExportPreview.tsx` VirtuosoHeader.

### 7. Loading Overlay Dark Mode Fix

**Problem:** `bg-white/40 dark:bg-black/20` uses hardcoded colors.

**Solution:** Change to `bg-surface/40 dark:bg-surface/40` in `ExportPreview.tsx`.

## Files Modified

| File | Change |
|------|--------|
| `resources/js/components/ui/Alert.tsx` | **New** — shadcn Alert component |
| `resources/js/components/ui/PanelHeader.tsx` | No changes needed (already supports icon + suffix) |
| `resources/js/components/export/ExportReadingOrder.tsx` | PanelHeader, Radix Collapsible, collapsed rail icon |
| `resources/js/components/export/TemplateCard.tsx` | Redesigned card layout |
| `resources/js/components/export/ExportSettings.tsx` | Add Alert below template selector |
| `resources/js/components/export/ExportPreview.tsx` | Fix tracking + loading overlay |
| `resources/js/i18n/en/export.json` | Add alert i18n keys |

## Files NOT Modified

- `export.tsx` (page) — no changes, all work is in child components
- `CustomizePanel.tsx` — no changes needed
- `TemplateSelector.tsx` — minor padding adjustment for taller cards may be needed
- Settings layout / spacing — kept as-is (px-11)

## Out of Scope

- Export button position (stays scroll-to-bottom)
- Paper color `#FFFEFA` (intentional for book preview)
- Card wrapping for settings sections (flat layout stays)
- Preview panel structure (header stays inside scroll)

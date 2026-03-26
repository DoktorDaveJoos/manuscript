# Export Page Design System Alignment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the Export page into sync with Manuscript's design system by replacing custom patterns with shared components.

**Architecture:** Seven targeted edits across six files plus one new Alert component. No structural changes to the page — all work is in child components. The Reading Order panel gets the biggest rewrite (PanelHeader + Radix Collapsible). Other changes are surgical.

**Tech Stack:** React 19, Radix UI (Collapsible), lucide-react, Tailwind v4, i18next

**Spec:** `docs/superpowers/specs/2026-03-25-export-design-system-alignment.md`

---

### Task 1: Create Alert UI component

**Files:**
- Create: `resources/js/components/ui/Alert.tsx`

- [ ] **Step 1: Create the Alert component**

```tsx
import { cva, type VariantProps } from 'class-variance-authority';
import { AlertCircle, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

const alertVariants = cva(
    'relative flex gap-3 rounded-lg border p-4 text-[13px]',
    {
        variants: {
            variant: {
                default: 'border-border bg-surface-card text-ink-soft',
                info: 'border-accent/30 bg-accent/5 text-ink-soft',
                destructive: 'border-delete/30 bg-delete/5 text-ink-soft',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

const VARIANT_ICONS = {
    default: null,
    info: <Info size={16} className="mt-0.5 shrink-0 text-accent" />,
    destructive: <AlertCircle size={16} className="mt-0.5 shrink-0 text-delete" />,
};

type AlertProps = React.HTMLAttributes<HTMLDivElement> &
    VariantProps<typeof alertVariants>;

function Alert({ className, variant = 'default', children, ...props }: AlertProps) {
    return (
        <div role="alert" className={cn(alertVariants({ variant }), className)} {...props}>
            {VARIANT_ICONS[variant ?? 'default']}
            <div className="flex flex-col gap-1">{children}</div>
        </div>
    );
}

function AlertTitle({ className, ...props }: React.HTMLAttributes<HTMLHeadingElement>) {
    return <h5 className={cn('text-[13px] font-semibold text-ink', className)} {...props} />;
}

function AlertDescription({ className, ...props }: React.HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-[12px] text-ink-muted', className)} {...props} />;
}

export { Alert, AlertTitle, AlertDescription };
```

- [ ] **Step 2: Verify it builds**

Run: `npx tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors related to Alert.tsx

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ui/Alert.tsx
git commit -m "feat: add Alert UI component (default, info, destructive variants)"
```

---

### Task 2: Redesign TemplateCard

**Files:**
- Modify: `resources/js/components/export/TemplateCard.tsx` (full rewrite, 45 lines)
- Modify: `resources/js/components/export/TemplateSelector.tsx:16` (adjust container padding)

- [ ] **Step 1: Rewrite TemplateCard**

Replace the entire component body in `TemplateCard.tsx` with:

```tsx
import type { TemplateDef } from '@/components/export/types';
import { cn } from '@/lib/utils';

const PREVIEW_TEXT =
    'The morning sun cast long shadows across the cobblestone path. She paused at the gate, clutching the letter that would change everything.';

interface TemplateCardProps {
    template: TemplateDef;
    isSelected: boolean;
    onClick: () => void;
}

export default function TemplateCard({
    template,
    isSelected,
    onClick,
}: TemplateCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex h-[200px] w-[160px] shrink-0 flex-col overflow-hidden rounded-lg border p-4 text-left transition-all',
                'bg-white',
                'shadow-[0_2px_8px_#00000008] dark:shadow-[0_2px_8px_#00000020]',
                isSelected
                    ? 'border-accent ring-2 ring-accent'
                    : 'border-border-light hover:border-border-strong',
            )}
        >
            <span
                className="text-[13px] font-semibold leading-tight text-ink"
                style={{ fontFamily: template.headingFont }}
            >
                {template.name}
            </span>
            <p
                className="mt-2 line-clamp-6 text-[9px] leading-[1.6] text-ink-muted"
                style={{ fontFamily: template.bodyFont }}
            >
                {PREVIEW_TEXT}
            </p>
        </button>
    );
}
```

- [ ] **Step 2: Adjust TemplateSelector container padding**

In `TemplateSelector.tsx`, change `pb-1` to `pb-2` to accommodate taller cards' box-shadow:

```tsx
// Line 16: change pb-1 → pb-2
<div className="flex gap-2.5 overflow-x-auto pb-2">
```

- [ ] **Step 3: Verify it builds**

Run: `npx tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/export/TemplateCard.tsx resources/js/components/export/TemplateSelector.tsx
git commit -m "refactor: redesign template cards as miniature book pages"
```

---

### Task 3: Add "More templates" Alert to ExportSettings

**Files:**
- Modify: `resources/js/components/export/ExportSettings.tsx:1-4` (add import), `:147-155` (insert Alert)
- Modify: `resources/js/i18n/en/export.json` (replace 2 orphaned keys with 2 new keys)
- Modify: `resources/js/i18n/de/export.json` (same key cleanup + add new keys)
- Modify: `resources/js/i18n/es/export.json` (same key cleanup + add new keys)

- [ ] **Step 1: Update i18n keys**

In `resources/js/i18n/en/export.json`:
- Replace the unused `"templateComingSoon"` key (line 13) and `"templateHint"` key (line 12) with the new Alert keys:

```json
"templateAlertTitle": "More templates on the way",
"templateAlertDescription": "We're working hard on new templates. Stay tuned for upcoming styles and layouts.",
```

Also remove `"templateComingSoon"` and `"templateHint"` from the other locale files:
- `resources/js/i18n/de/export.json` (lines 12-13)
- `resources/js/i18n/es/export.json` (lines 12-13)

Add equivalent translated keys for `templateAlertTitle` and `templateAlertDescription` in those files (use English as placeholder if unsure of translation).

- [ ] **Step 2: Add Alert import and usage in ExportSettings**

Add import at top of `ExportSettings.tsx`:

```tsx
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/Alert';
```

Insert the Alert between `TemplateSelector` and the `templateHint` paragraph. Replace lines 147-154 (the `<TemplateSelector>` through the `<p>` hint) with:

```tsx
                            <TemplateSelector
                                templates={templates}
                                selectedTemplate={template}
                                onChange={onTemplateChange}
                            />
                            <Alert variant="info">
                                <AlertTitle>{t('templateAlertTitle')}</AlertTitle>
                                <AlertDescription>{t('templateAlertDescription')}</AlertDescription>
                            </Alert>
```

Remove the `<p className="text-[11px] text-ink-faint">{t('templateHint')}</p>` — the Alert replaces it as the information below the template selector.

- [ ] **Step 3: Verify it builds**

Run: `npx tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/export/ExportSettings.tsx resources/js/i18n/en/export.json
git commit -m "feat: add 'more templates coming' info alert on export page"
```

---

### Task 4: Rewrite ExportReadingOrder — PanelHeader + Radix Collapsible + collapsed rail

**Files:**
- Modify: `resources/js/components/export/ExportReadingOrder.tsx` (major rewrite)

This is the largest task. It changes: imports, removes `CollapsibleSection`/`SectionHeader` helper components, replaces header with `PanelHeader`, converts three sections to Radix `Collapsible`, and updates the collapsed rail.

- [ ] **Step 1: Update imports**

Replace the import block (lines 1-36) with:

```tsx
import {
    DndContext,
    DragOverlay,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronDown,
    GripVertical,
    PanelLeftClose,
    PanelLeftOpen,
    TableOfContents,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { show as publishShow } from '@/actions/App/Http/Controllers/PublishController';
import { index as settingsIndex } from '@/actions/App/Http/Controllers/SettingsController';
import type {
    ChapterRow,
    MatterItem,
    StorylineRef,
} from '@/components/export/types';
import Checkbox from '@/components/ui/Checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/Collapsible';
import PanelHeader from '@/components/ui/PanelHeader';
import SectionLabel from '@/components/ui/SectionLabel';
import { useResizablePanel } from '@/hooks/useResizablePanel';
import { cn } from '@/lib/utils';
```

Note: Removed `ChevronRight`, `PropsWithChildren`, added `PanelLeftClose`, `PanelLeftOpen`, `TableOfContents`, `Collapsible*`, `PanelHeader`.

- [ ] **Step 2: Replace SectionHeader component**

Delete the old `SectionHeader` function (lines 173-202) and `CollapsibleSection` function (lines 204-218). Replace with a new `SectionHeader` that works as a `CollapsibleTrigger`:

```tsx
function SectionHeader({
    children,
    count,
}: {
    children: React.ReactNode;
    count?: number;
}) {
    return (
        <button
            type="button"
            className="group flex w-full items-center gap-1.5"
        >
            <ChevronDown
                className="h-3 w-3 text-ink-faint transition-transform group-data-[state=closed]:-rotate-90"
            />
            <SectionLabel>{children}</SectionLabel>
            {count !== undefined && (
                <span className="text-[11px] text-ink-faint">{count}</span>
            )}
        </button>
    );
}
```

The chevron now rotates via `group-data-[state=closed]:-rotate-90`. The `CollapsibleTrigger asChild` will set the `data-state` attribute on this button.

- [ ] **Step 3: Replace the collapsed rail**

Replace the collapsed return block (the `if (collapsed)` block, lines 312-327) with:

```tsx
    if (collapsed) {
        return (
            <aside className="flex h-full w-9 shrink-0 flex-col items-center border-r border-border-subtle bg-white pt-3 dark:bg-surface-card">
                <button
                    type="button"
                    onClick={toggleCollapsed}
                    className="flex h-7 w-7 items-center justify-center rounded-md text-ink-faint transition-colors hover:bg-neutral-bg hover:text-ink-muted"
                >
                    <PanelLeftOpen size={14} />
                </button>
                <TableOfContents size={14} className="mt-3 text-ink-faint" />
            </aside>
        );
    }
```

- [ ] **Step 4: Replace the header section**

Replace the custom header block (lines 343-360) with `PanelHeader`:

```tsx
            {/* Header */}
            <PanelHeader
                title={t('readingOrder')}
                icon={<TableOfContents size={14} className="text-ink-faint" />}
                suffix={
                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        className="flex size-6 items-center justify-center rounded text-ink-muted transition-colors hover:text-ink-soft"
                    >
                        <PanelLeftClose size={14} />
                    </button>
                }
            />
```

- [ ] **Step 5: Convert three sections to Radix Collapsible**

Replace the scrollable content area (lines 362-489) with:

```tsx
            {/* Scrollable content */}
            <div className="flex-1 overflow-y-auto">
                {/* Front matter */}
                <Collapsible defaultOpen>
                    <div className="border-b border-border-subtle">
                        <div className="px-4 pt-3 pb-2">
                            <CollapsibleTrigger asChild>
                                <SectionHeader>
                                    {t('frontMatter')}
                                </SectionHeader>
                            </CollapsibleTrigger>
                        </div>
                        <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out]">
                            <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                                {frontMatter.map((item) => (
                                    <MatterRow
                                        key={item.id}
                                        item={item}
                                        onToggle={() =>
                                            onToggleFrontMatter(item.id)
                                        }
                                        fromUrl={pageUrl}
                                        publishUrl={bookPublishUrl}
                                    />
                                ))}
                            </div>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                {/* Chapters */}
                <Collapsible defaultOpen>
                    <div className="border-b border-border-subtle">
                        <div className="px-4 pt-3 pb-2">
                            <CollapsibleTrigger asChild>
                                <SectionHeader count={orderedChapters.length}>
                                    {t('chapters')}
                                </SectionHeader>
                            </CollapsibleTrigger>
                        </div>
                        <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out]">
                            <div className="flex flex-col px-4 pt-1 pb-3.5">
                                <DndContext
                                    sensors={sensors}
                                    collisionDetection={closestCenter}
                                    onDragStart={handleDragStart}
                                    onDragEnd={handleDragEnd}
                                >
                                    <SortableContext
                                        items={orderedChapters.map(
                                            (ch) => `export-${ch.id}`,
                                        )}
                                        strategy={verticalListSortingStrategy}
                                    >
                                        {orderedChapters.map((chapter, index) => (
                                            <SortableChapterRow
                                                key={chapter.id}
                                                chapter={chapter}
                                                index={index}
                                                storyline={storylineMap.get(
                                                    chapter.storyline_id,
                                                )}
                                                checked={selectedChapterIds.has(
                                                    chapter.id,
                                                )}
                                                onToggle={() =>
                                                    onToggleChapter(chapter.id)
                                                }
                                            />
                                        ))}
                                    </SortableContext>

                                    <DragOverlay>
                                        {activeChapter && (
                                            <div className="flex items-center gap-2 rounded bg-white px-2 py-1 opacity-95 shadow-[0_4px_16px_#0000001F,0_0_0_1px_#0000000A] dark:bg-surface-card">
                                                <span className="flex shrink-0 items-center text-ink-faint">
                                                    <GripVertical className="h-3 w-3" />
                                                </span>
                                                <span
                                                    className="h-1.5 w-1.5 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            activeStoryline?.color ??
                                                            '#737373',
                                                    }}
                                                />
                                                <span className="min-w-0 flex-1 truncate text-[12px] text-ink-soft">
                                                    {activeChapter.title}
                                                </span>
                                            </div>
                                        )}
                                    </DragOverlay>
                                </DndContext>
                            </div>
                        </CollapsibleContent>
                    </div>
                </Collapsible>

                {/* Back matter */}
                <Collapsible defaultOpen>
                    <div className="border-b border-border-subtle">
                        <div className="px-4 pt-3 pb-2">
                            <CollapsibleTrigger asChild>
                                <SectionHeader>
                                    {t('backMatter')}
                                </SectionHeader>
                            </CollapsibleTrigger>
                        </div>
                        <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-[collapsible-up_200ms_ease-out] data-[state=open]:animate-[collapsible-down_200ms_ease-out]">
                            <div className="flex flex-col gap-1 px-4 pt-2 pb-3.5">
                                {backMatter.map((item) => (
                                    <MatterRow
                                        key={item.id}
                                        item={item}
                                        onToggle={() => onToggleBackMatter(item.id)}
                                        fromUrl={pageUrl}
                                        publishUrl={bookPublishUrl}
                                    />
                                ))}
                            </div>
                        </CollapsibleContent>
                    </div>
                </Collapsible>
            </div>
```

- [ ] **Step 6: Remove dead state**

In the main component function, delete `expandedSections` state and `toggleSection` callback (lines 241-260). These are no longer needed since Radix Collapsible manages its own state internally.

- [ ] **Step 7: Verify it builds**

Run: `npx tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/export/ExportReadingOrder.tsx
git commit -m "refactor: use PanelHeader, Radix Collapsible, and icon rail in Reading Order panel"
```

---

### Task 5: Fix ExportPreview tracking and loading overlay

**Files:**
- Modify: `resources/js/components/export/ExportPreview.tsx:382` (tracking), `:463` (overlay)

- [ ] **Step 1: Fix tracking in VirtuosoHeader**

In `ExportPreview.tsx` line 382, change:
```
tracking-[0.01em]
```
to:
```
tracking-[0.08em]
```

- [ ] **Step 2: Fix loading overlay colors**

In `ExportPreview.tsx` line 463, change:
```
bg-white/40 dark:bg-black/20
```
to:
```
bg-surface/40
```

- [ ] **Step 3: Verify it builds**

Run: `npx tsc --noEmit --pretty 2>&1 | head -20`
Expected: No errors

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/export/ExportPreview.tsx
git commit -m "fix: align preview header tracking and loading overlay with design system"
```

---

### Task 6: Run Pint and final build verification

**Files:** All modified PHP files (none expected), all modified TS/TSX files

- [ ] **Step 1: Run TypeScript check**

Run: `npx tsc --noEmit --pretty`
Expected: Clean pass (or only pre-existing errors unrelated to export)

- [ ] **Step 2: Run ESLint on changed files**

Run: `npx eslint resources/js/components/export/ resources/js/components/ui/Alert.tsx --max-warnings=0`
Expected: Clean pass

- [ ] **Step 3: Run build**

Run: `npm run build 2>&1 | tail -5`
Expected: Build succeeds

- [ ] **Step 4: Commit any lint/format fixes if needed**

```bash
git add -A && git commit -m "chore: lint and format fixes"
```
(Only if there were fixes to commit)

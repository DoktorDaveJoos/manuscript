# Editorial Review UX Improvements

## Problem

The editorial review produces rich, structured data across 8 sections, but the current UI has three issues:

1. **Wall of text** — Expanding a section dumps summary + all findings + all recommendations at once. No progressive disclosure.
2. **No actionability** — Findings reference chapters but aren't clickable. No way to track what you've addressed.
3. **Context switching** — You read a finding on the review page, then manually navigate to the editor to fix it. No connection between the two.

## Design Decisions

All decisions resolved via grill-me session. No open questions.

---

## Part 1: Finding Resolution Tracking

### 1.1 Migration — `resolved_findings` column

Add a `resolved_findings` JSON column to `editorial_reviews` table.

```php
// database/migrations/xxxx_add_resolved_findings_to_editorial_reviews_table.php
Schema::table('editorial_reviews', function (Blueprint $table) {
    $table->json('resolved_findings')->nullable()->after('top_improvements');
});
```

**Value:** Array of deterministic finding keys. Each key is a hash of `{section_type}:{first 100 chars of description}`.

### 1.2 Finding Key Generation

Generate keys server-side during synthesis (in `RunEditorialReviewJob` after saving sections) and client-side for matching.

**Backend** — Add a static helper on `EditorialReviewSection` or a utility:

```php
public static function findingKey(string $sectionType, string $description): string
{
    return hash('xxh32', $sectionType . ':' . mb_substr($description, 0, 100));
}
```

**Frontend** — Mirror in `editorial-constants.ts`:

```typescript
export function findingKey(sectionType: string, description: string): string {
    // xxh32 equivalent or simple hash — must match backend
}
```

> **Alternative:** Generate keys server-side only. When sections are loaded, each finding already has its key pre-computed and included in the JSON. Avoids hash parity issues entirely. The synthesis agent output schema stays the same; keys are added post-save.

**Recommendation:** Server-side only. Add a `key` field to each finding in the `findings` JSON column after synthesis completes. Frontend just reads `finding.key`.

### 1.3 Resolve/Unresolve Endpoint

New endpoint on `EditorialReviewController`:

```
PATCH /books/{book}/ai/editorial-review/{review}/findings/{key}/resolve
PATCH /books/{book}/ai/editorial-review/{review}/findings/{key}/unresolve
```

Or a single toggle:

```
POST /books/{book}/ai/editorial-review/{review}/findings/toggle
Body: { key: string }
```

**Response:** Updated `resolved_findings` array.

### 1.4 Type Updates

```typescript
// In models.ts
export type EditorialReviewFinding = {
    key: string;              // NEW — deterministic hash
    severity: FindingSeverity;
    description: string;
    chapter_references: number[];
    recommendation: string;
};

export type EditorialReview = {
    // ... existing fields ...
    resolved_findings: string[] | null;  // NEW — array of finding keys
};
```

### 1.5 Model Cast Update

```php
// EditorialReview.php
protected function casts(): array
{
    return [
        // ... existing casts ...
        'resolved_findings' => 'array',
    ];
}
```

---

## Part 2: Review Page — Wall of Text Fix

### 2.1 Two-Level Accordion in `EditorialReviewSection`

**Current behavior:** Section expands → shows summary + all findings fully rendered + recommendations list.

**New behavior:** Section expands → shows summary + truncated finding headlines only. Each finding is individually expandable.

**Finding headline (collapsed):**
```
● [critical] [Characters]  The antagonist's motivation shifts without expl...  Ch. 4, 7  ▸
☐  ← checkbox for resolve
```

- Severity dot (using existing `severityDotColor`)
- Truncated description (~80 chars + ellipsis)
- Chapter reference badges
- Expand chevron
- Checkbox (resolve/unresolve)

**Finding detail (expanded):**
```
☑ [critical]  Ch. 4, 7
The antagonist's motivation shifts without explanation between chapters 4 and 7.
This creates a jarring disconnect for readers who...

Recommendation:
Establish a turning point or internal conflict that explains the shift.
```

- Full description
- Recommendation inline (no separate recommendations list)

### 2.2 Remove Section-Level Recommendations

The separate `recommendations[]` list at the bottom of each section is removed. Each finding's individual `recommendation` field provides the specific, actionable advice. The section `summary` provides the high-level editorial overview.

**Backend:** The `recommendations` column stays in the DB (no migration needed to remove it). Just stop rendering it on the frontend.

**Frontend:** Remove the recommendations rendering block from `EditorialReviewSection.tsx`.

### 2.3 Resolved Finding Styling

Resolved findings in the section list get:
- `line-through` on the headline text
- `opacity-50` on the entire finding row
- Checkbox checked
- Still expandable, still in the same position (no reordering)

### 2.4 Section Header Updates

**Current:** Section name + score badge.

**New:** Section name + score badge + "N remaining" count.

```
Characters · 72 · 3 remaining
Characters · 72 · ✓ All resolved     ← when count hits 0
```

- "N remaining" = total findings minus resolved findings for that section
- "All resolved" shown in success color with checkmark icon
- Uses `text-ink-faint` for the remaining count, success color for "All resolved"

---

## Part 3: Chapter Progress Strip

### 3.1 New Component — `ChapterProgressStrip`

**Location:** `resources/js/components/editorial-review/ChapterProgressStrip.tsx`

**Position:** Below the executive summary card, above the section accordions on the review page.

**Props:**
```typescript
{
    chapters: Chapter[];
    sections: EditorialReviewSection[];
    resolvedFindings: string[] | null;
    bookId: number;
}
```

**Layout:** Horizontal scrollable row of chapter cells.

**Each cell shows:**
- Chapter number (reader_order + 1)
- Mini progress indicator (e.g., circular ring or fractional bar)
- Resolved / total findings count for that chapter

**Behavior:**
- Click a chapter cell → `router.visit` to editor with that chapter loaded
- Chapters with zero findings: dimmed/muted but still shown for completeness
- Finding count per chapter: count all findings across all sections where `chapter_references` includes that chapter's ID

### 3.2 Chapter-to-Findings Mapping

Utility function in `editorial-constants.ts` or a new `editorial-utils.ts`:

```typescript
export function findingsByChapter(
    sections: EditorialReviewSection[],
    chapters: Chapter[]
): Map<number, { total: number; resolved: number; findings: EditorialReviewFinding[] }>
```

Iterates all sections' findings, groups by chapter ID from `chapter_references`. A single finding referencing multiple chapters appears in each chapter's group.

---

## Part 4: Clickable Chapter References

### 4.1 Chapter Badges → Links

In `EditorialReviewSection.tsx`, the chapter reference badges (currently plain text "Ch. 4, 7") become clickable links.

**Click behavior:** `router.visit` to the editor page with the clicked chapter pre-selected.

**Route:** Use Wayfinder-generated route for `ChapterController@show` (or equivalent editor route), passing `book` and `chapter` params.

**Styling:** Underline on hover, cursor-pointer. Same badge styling but interactive.

---

## Part 5: Editor Review Panel

### 5.1 New Panel — `EditorialReviewPanel`

**Location:** `resources/js/components/editor/EditorialReviewPanel.tsx`

**Icon:** `list-todo` (from Lucide)

**Panel ID:** Add `'editorial'` to the `PanelId` type in `chapters/show.tsx`:

```typescript
type PanelId = 'notes' | 'ai' | 'chat' | 'editorial';
```

**Registration in AccessBar:**
- Icon: `ListTodo`
- Label: "Editorial Review" (i18n)
- Badge: unresolved finding count for current chapter (number badge on icon)
- Always visible (even when count is 0)

**SlidePanel config:**
- `defaultWidth={280}`
- `storageKey="manuscript:editorial-panel-width"`

### 5.2 Panel Data Loading

The panel needs the latest completed editorial review for the current book. Two approaches:

**A) Pass as Inertia shared prop** — Add to `HandleInertiaRequests.php` or the chapter show controller. Lazy-loaded.

**B) Fetch on panel open** — API call when the panel is first opened or chapter changes.

**Recommendation: A** — Pass as a lazy Inertia prop from the chapter show controller. The data is small (review ID + resolved_findings + findings arrays). Avoid extra API calls.

```php
// In chapter show controller
'editorialReview' => Inertia::lazy(fn () => $book->editorialReviews()
    ->where('status', 'completed')
    ->latest()
    ->with('sections')
    ->first()),
```

### 5.3 Panel Content

**When editorial review exists and has findings for current chapter:**

Flat list sorted by severity (critical → warning → suggestion). Each item:

```
☐ [critical] [Characters]
  The antagonist's motivation shifts without explanation...
  ▸ Recommendation
```

- Checkbox to resolve/unresolve (same endpoint as review page)
- Severity badge (using existing `severityBadgeVariant`)
- Section badge (e.g., "Characters", "Pacing") — small, muted
- Truncated description (expandable on click)
- Recommendation shown on expand

**When no findings for current chapter:**

Empty state: checkmark icon + "No findings for this chapter" text.

**When no editorial review exists:**

Empty state: "No editorial review yet" + link to the editorial review page.

### 5.4 Badge on AccessBar Icon

The `ListTodo` icon in the AccessBar shows a small badge with the count of **unresolved** findings for the current chapter.

- Count > 0: Show numeric badge (e.g., red/accent dot with number)
- Count = 0: No badge (but icon remains visible)
- No editorial review: No badge

---

## Part 6: i18n Updates

Add to `resources/js/i18n/en/editorial-review.json`:

```json
{
    "section.remaining": "{{count}} remaining",
    "section.allResolved": "All resolved",
    "finding.recommendation": "Recommendation",
    "chapterStrip.title": "Chapter Progress",
    "chapterStrip.findings": "{{resolved}}/{{total}}",
    "panel.title": "Editorial Review",
    "panel.emptyChapter": "No findings for this chapter",
    "panel.noReview": "No editorial review yet",
    "panel.viewReview": "View editorial review",
    "finding.resolve": "Mark as resolved",
    "finding.unresolve": "Mark as unresolved"
}
```

---

## Implementation Order

### Phase 1: Foundation (finding keys + resolution tracking)
1. Migration: add `resolved_findings` JSON column
2. Generate finding keys in `RunEditorialReviewJob` post-synthesis
3. Add `key` to `EditorialReviewFinding` type
4. Add resolve/unresolve endpoint
5. Update model casts
6. Run migration against both databases

### Phase 2: Review Page — Progressive Disclosure
7. Refactor `EditorialReviewSection` to two-level accordion (truncated headlines + expandable detail)
8. Remove section-level recommendations rendering
9. Add resolve checkboxes to findings
10. Add resolved styling (strikethrough + dimmed)
11. Update section headers with "N remaining" / "All resolved"

### Phase 3: Chapter Progress Strip
12. Build `ChapterProgressStrip` component
13. Add `findingsByChapter` utility
14. Integrate into `EditorialReviewReport` between summary and sections
15. Wire click → editor navigation

### Phase 4: Clickable Chapter References
16. Make chapter badges in findings clickable → navigate to editor

### Phase 5: Editor Review Panel
17. Create `EditorialReviewPanel` component
18. Add `'editorial'` panel to editor layout + AccessBar
19. Pass editorial review as lazy Inertia prop from chapter controller
20. Wire resolve/unresolve to same endpoint
21. Add badge count on AccessBar icon

### Phase 6: Polish
22. Add all i18n keys
23. Test resolved state sync between review page and editor panel
24. Verify two-level accordion keyboard accessibility

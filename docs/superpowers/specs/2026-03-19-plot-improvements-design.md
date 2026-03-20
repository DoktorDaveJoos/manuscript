# Plot Improvements: Templates, Richer Cards, Character Linking

**Date**: 2026-03-19
**Status**: Approved

## Overview

Three targeted improvements to the Plot feature based on competitive analysis against Plottr, NovelCrafter, Campfire, and other plotting tools:

1. **New structure templates** — Save the Cat and Story Circle
2. **Richer swim lane cards** — description preview, character initials, chapter word count
3. **Character-PlotPoint relationship** — many-to-many pivot with role

## 1. New Structure Templates

### Save the Cat (Blake Snyder, 15 beats across 3 acts)

**Act 1 — Setup** (color: `#B87333`):
| Beat | Type |
|------|------|
| Opening Image | setup |
| Theme Stated | setup |
| Set-Up | setup |
| Catalyst | conflict |
| Debate | conflict |

**Act 2 — Confrontation** (color: `#8B6914`):
| Beat | Type |
|------|------|
| Break Into Two | turning_point |
| B Story | setup |
| Fun and Games | conflict |
| Midpoint | turning_point |
| Bad Guys Close In | conflict |
| All Is Lost | conflict |
| Dark Night of the Soul | conflict |

**Act 3 — Resolution** (color: `#6B4423`):
| Beat | Type |
|------|------|
| Break Into Three | turning_point |
| Finale | resolution |
| Final Image | resolution |

### Story Circle (Dan Harmon, 8 steps across 2 acts)

**Act 1 — The Descent** (color: `#B87333`):
| Beat | Type |
|------|------|
| You | setup |
| Need | conflict |
| Go | turning_point |
| Search | conflict |

**Act 2 — The Return** (color: `#8B6914`):
| Beat | Type |
|------|------|
| Find | turning_point |
| Take | conflict |
| Return | resolution |
| Change | resolution |

### Implementation

- Add raw template data to `resources/js/lib/plot-templates.ts`
- Add i18n keys to `resources/js/i18n/en/plot.json` (and de/es)
- Update `SetupPlotStructureRequest` to include `save_the_cat` and `story_circle` in the `Rule::in` validation for the `template` field
- No component changes — `PlotEmptyState` and `PlotWizardModal` already render templates dynamically via `getPlotTemplates(t)`

## 2. Richer Swim Lane Cards

### Current State

`PlotPointCard` displays: status dot, title, type badge.

### Proposed Card Layout

```
┌─────────────────────┐
│ ● Title of the beat │
│ First line of the   │
│ description text...  │
│ ┌──────┐  1.2k words│
│ │Setup │            │
│ └──────┘   ⓐⓑⓒ +2  │
└─────────────────────┘
```

### New Elements

1. **Description preview**: First ~60 characters, truncated with ellipsis. Muted text, smaller size. Only rendered when description exists.
2. **Chapter word count**: Displayed next to type badge as "1.2k words". Only shown when a chapter is linked via `intended_chapter_id` or `actual_chapter_id`.
3. **Character initials**: Row of small circles showing first letter of each tagged character's name. Max 3 visible with "+N" overflow indicator.

### Data Requirements

- `PlotPoint` TypeScript type gains `characters?: Character[]`
- `PlotController@index` must eager-load `plotPoints.characters`
- Chapter word count is resolved **client-side** by looking up `intended_chapter_id` / `actual_chapter_id` against the already-loaded `chapters` array (avoids extra eager-loading)
- `PlotPointCard` receives an additional `chapterWordCount?: number` prop, computed by the parent from the chapters collection
- Description preview uses CSS `line-clamp-2` rather than a hard character count for natural truncation

### Sizing

Card width remains at the current swim lane column width (240px). The card grows vertically to accommodate new content. Empty plot points (no description, no characters, no chapter) remain the same compact size as today.

## 3. Character-PlotPoint Pivot Table

### Migration

New `character_plot_point` table:

```
character_id  — foreign key → characters (cascadeDelete)
plot_point_id — foreign key → plot_points (cascadeDelete)
role          — string, default 'key' (enum: key, supporting, mentioned)
timestamps
unique constraint on (character_id, plot_point_id)
```

### Backend Changes

**PlotPoint model** — new relationship:
```php
public function characters(): BelongsToMany
{
    return $this->belongsToMany(Character::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

**Character model** — new relationship:
```php
public function plotPoints(): BelongsToMany
{
    return $this->belongsToMany(PlotPoint::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

**PlotPointController@update** — accept optional `characters` array of `{id, role}` objects and use `sync()` with pivot data to manage the relationship.

**PlotController@index** — eager-load `plotPoints.characters`. Also pass the full book `characters` list as a separate prop for the DetailPanel's "Add character" dropdown.

### TypeScript Types

```typescript
// In models.ts
export type CharacterPlotPointRole = 'key' | 'supporting' | 'mentioned';

// PlotPoint type gains:
characters?: (Character & { pivot: CharacterPlotPointPivot })[];

// New pivot type:
export type CharacterPlotPointPivot = {
    character_id: number;
    plot_point_id: number;
    role: CharacterPlotPointRole;
};
```

### Detail Panel Changes

Add a "Characters" section below the Status dropdown:
- Shows tagged characters with their role as a small badge
- Add button opens a dropdown of book characters (filtered to exclude already-tagged)
- Click to remove with confirmation or just a remove icon
- Role can be changed via a small select next to each character name

### New PHP Enum

`App\Enums\CharacterPlotPointRole` — consistent with existing enums (`PlotPointType`, `PlotPointStatus`, etc.):
```php
enum CharacterPlotPointRole: string
{
    case Key = 'key';
    case Supporting = 'supporting';
    case Mentioned = 'mentioned';
}
```

### Form Request

`UpdatePlotPointRequest` gains optional `characters` validation:
```php
'characters' => ['sometimes', 'array'],
'characters.*.id' => ['required', 'integer', Rule::exists('characters', 'id')->where('book_id', $this->route('book')->id)],
'characters.*.role' => ['required', 'string', Rule::in(['key', 'supporting', 'mentioned'])],
```

The `exists` rule is scoped to the current book to prevent cross-book character assignment.

### Migration Note

Per project convention, the migration must be run against both the default database and NativePHP:
```bash
php artisan migrate --no-interaction
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

## Files Changed

### New Files
- `database/migrations/XXXX_create_character_plot_point_table.php`
- `app/Enums/CharacterPlotPointRole.php`

### Modified Files
- `resources/js/lib/plot-templates.ts` — add Save the Cat + Story Circle templates
- `resources/js/i18n/en/plot.json` — add template i18n keys
- `resources/js/i18n/de/plot.json` — add template i18n keys
- `resources/js/i18n/es/plot.json` — add template i18n keys
- `resources/js/components/plot/PlotPointCard.tsx` — add description, characters, word count
- `resources/js/components/plot/DetailPanel.tsx` — add Characters section
- `resources/js/types/models.ts` — add CharacterPlotPointPivot, CharacterPlotPointRole, update PlotPoint type
- `app/Models/PlotPoint.php` — add characters() relationship
- `app/Models/Character.php` — add plotPoints() relationship
- `app/Http/Controllers/PlotController.php` — eager-load characters, pass book characters list
- `app/Http/Controllers/PlotPointController.php` — handle characters sync with pivot data
- `app/Http/Requests/UpdatePlotPointRequest.php` — add characters validation (scoped to book)
- `app/Http/Requests/SetupPlotStructureRequest.php` — add new template keys to Rule::in
- `resources/js/pages/plot/index.tsx` — pass characters data through, compute chapter word counts

### Pencil Design Updates
- Update "Plot Timeline — Refined" to show richer cards
- Update "Plot — Empty State" to show 5 templates (add Save the Cat, Story Circle)
- Update "Plot — Wizard Modal (Step 1)" to show 5 template options

## Testing

### Template Tests
- Feature test for Save the Cat template creation via PlotSetupController (3 acts, 15 beats)
- Feature test for Story Circle template creation via PlotSetupController (2 acts, 8 beats)

### Character-PlotPoint Sync Tests
- Attach characters to a plot point via update
- Sync replaces previous characters (idempotent)
- Detach all characters by sending empty array
- Validation rejects non-existent character IDs
- Validation rejects characters from other books (scoped exists rule)
- Role is persisted correctly on the pivot

### Regression
- Verify existing plot tests still pass

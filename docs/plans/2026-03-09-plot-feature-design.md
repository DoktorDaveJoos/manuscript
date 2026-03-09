# Plot Feature — Full Reimagining

## Vision

The Plot page becomes the strategic command center for story structure. Authors plan, visualize, and analyze their narrative across multiple views — with optional AI that acts as an analyst, not a ghostwriter. This is the feature that makes Manuscript a category-defining tool: plotting + writing + AI analysis in one app, zero tool-switching.

## Views

### 1. Swimlane Timeline (default view)

The primary Plot experience. A visual grid showing how storylines weave together across the manuscript.

**Structure:**
- **X-axis:** Acts as major column groups, chapters as columns within each act
- **Y-axis:** Storylines as horizontal lanes, color-coded by storyline color
- **Cells:** Plot point cards sit at storyline × chapter intersections
- Empty cells are visible — gaps in a storyline are immediately obvious

**Interactions:**
- **Click empty cell** → create a new plot point at that storyline × chapter intersection
- **Click a card** → detail panel slides open on the right with full metadata (title, description, type, status, linked chapter, characters) and a "Jump to chapter" button that navigates to the Editor
- **Drag cards** → rearrange between cells to restructure the story (move beats between chapters or storylines)
- **Drag between cards** → draw cause-and-effect connection lines. "This setup in Ch. 1 pays off in this resolution in Ch. 4." Connections render as subtle arrows/lines on the timeline. This is a key differentiator — no competitor offers this.

**With AI enabled:**
- Inline indicators on the timeline: gap highlights, imbalance warnings, quiet storyline detection
- These reflect cached results from the last AI analysis run (not live/automatic)

### 2. Tension Arc (integrated strip, AI-only)

A compact visual curve that sits directly above the swimlane timeline, aligned to the same chapter columns.

**Key behaviors:**
- **Not visible by default.** The author must explicitly generate it via the AI Action Sidebar ("Generate Tension Arc" action)
- Shows rising/falling tension across the manuscript as a smooth curve
- Aligned with timeline columns so tension dips visually correspond to the beats below
- Collapsible — can be hidden after generation to reclaim vertical space
- Shows cached result with "Last generated: [timestamp]" indicator
- **Hidden entirely** when AI is not configured or disabled in settings

**Data source:** AI analyzes manuscript text (chapter content, scene content) and plot point types to produce per-chapter tension scores.

### 3. Plot Points List (tab view)

A structured, scannable list of all plot points — the writer's companion for active drafting.

**Structure:**
- Tab switching at the top of the Plot page: "Timeline" (default) | "List"
- Plot points grouped by Acts, matching the current Paper design
- Each plot point shows: title, description, type badge (Setup/Conflict/Turning Point/Resolution/Worldbuilding), status dot (Planned/Fulfilled/Abandoned), linked chapter
- Storyline filter dropdown ("All storylines" or specific one)
- Add new plot point via "+" button

**AI enrichment (when available):**
- Results from AI analysis displayed contextually (health score, findings, suggested beats)
- Same cached results as the timeline — running analysis in one view updates both

### 4. Editor Sidebar — Chapter Beats (condensed)

A compact plot points panel in the Editor sidebar, filtered to the currently active chapter.

**Structure:**
- Shows only plot points linked to the active chapter
- Quick status toggling: click to cycle Planned → Fulfilled → Abandoned
- Plot point type badge and title
- Tapping a plot point could expand to show description inline
- "Add beat" quick action to create a new plot point for the current chapter

**Purpose:** Contextual awareness while writing — "what beats am I supposed to hit in this chapter?"

## AI Action Sidebar

A collapsible drawer on the right side of the Plot page (consistent with the Editor sidebar pattern).

**Behavior:**
- Collapsed by default — timeline gets maximum horizontal space
- Author clicks to open when they want to run or review AI actions
- **Completely hidden** when AI is disabled in app settings (existing toggle)
- Results persist between sessions with "Last run: [timestamp]" indicators

**Available actions:**

| Action | What it does | Output |
|--------|-------------|--------|
| **Generate Tension Arc** | Analyzes manuscript content to produce per-chapter tension scores | Tension curve appears above timeline |
| **Run Plot Health Analysis** | Evaluates overall plot structure | Health score (0-100), continuity %, act balance %, resolution rate |
| **Detect Plot Holes** | Chekhov checks, unfired setups, abandoned threads, deviations | List of findings with severity and descriptions |
| **Suggest Next Beats** | Recommends plot points based on current structure and gaps | Numbered list of suggested beats with rationale |

Each action is a distinct button. Results display in the sidebar below the action that generated them.

**Token awareness:** Actions consume AI tokens. The author explicitly triggers each one — nothing runs automatically. This respects the author's budget and agency.

## Cause-and-Effect Connections

The key differentiator no competitor offers.

**Data model:**
- A `plot_point_connections` table linking two plot points: `source_plot_point_id` → `target_plot_point_id`
- Connection type: `causes`, `sets_up`, `resolves`, `contradicts`
- Optional description field for the connection

**Timeline visualization:**
- Connections render as subtle curved arrows between cards on the swimlane
- Hovering a card highlights its connections (upstream causes, downstream effects)
- Different line styles for connection types (solid for causes, dashed for sets_up, etc.)

**Detail panel integration:**
- When viewing a plot point's detail panel, connections are listed: "Set up by: [card]" / "Leads to: [card]"
- Click a connected card to navigate to it

**Creation:**
- Author drags from one card's connection handle to another card
- A small dialog confirms the connection type
- Can also be created from the detail panel via a "Connect to..." action

## Data Model Changes

### New table: `plot_point_connections`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| book_id | foreignId | Parent book |
| source_plot_point_id | foreignId | The cause/setup |
| target_plot_point_id | foreignId | The effect/payoff |
| type | enum | causes, sets_up, resolves, contradicts |
| description | text, nullable | Optional note about the connection |
| timestamps | | Created/updated at |

### New fields on `plot_points`

| Column | Type | Description |
|--------|------|-------------|
| tension_score | integer, nullable | AI-generated tension score (0-100) for the tension arc |

## AI Integration Rules

1. **No AI, full functionality:** Timeline, list, drag-and-drop, connections, detail panel — all work without AI
2. **AI is opt-in per action:** Author explicitly triggers each analysis. No background token consumption
3. **Results are cached:** Displayed with timestamps, persist until re-run
4. **AI disabled = sidebar hidden:** When AI is toggled off in settings, the action sidebar doesn't render at all
5. **PRO license required:** AI actions require active PRO license (existing `useLicense` / `useAiFeatures` hooks)

## View Switching

- Plot page URL: `/books/{book}/plot`
- Tab state preserved via query param: `?view=timeline` (default) | `?view=list`
- AI sidebar open/close state persisted in local storage
- Tension arc collapsed/expanded state persisted in local storage

## Key Design Principles

- **Progressive disclosure:** Timeline is simple at first glance. Complexity reveals on interaction (connections, detail panel, tension arc)
- **Spatial thinking:** Authors think about stories spatially. The timeline lets them *see* their story's shape
- **Low friction:** Click an empty cell to add a beat. Drag to rearrange. No forms or modals for basic operations
- **AI as analyst:** AI finds problems and suggests solutions. It never writes plot points or makes changes without the author's explicit action
- **Tight manuscript coupling:** Every plot point links to a chapter. "Jump to chapter" bridges planning and writing seamlessly

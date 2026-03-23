# Typewriter Mode Implementation Research

## 1. What Typewriter Mode Should Cover

### Core Behavior

Typewriter mode keeps the cursor/caret at a **fixed vertical position** on screen (typically the vertical center) while the document scrolls beneath it. The name comes from physical typewriters where the paper moves, not the typebar.

### Standard Behaviors

- **During typing**: The current line remains at the configured vertical offset (e.g., 50% of the viewport). As text is added, the document scrolls up to maintain cursor position.
- **Arrow key navigation**: Up/down arrow keys scroll the document to keep the cursor centered, rather than the cursor moving within a static viewport.
- **Enter key**: New line creation scrolls the document up by one line height.
- **Backspace/delete across lines**: When deletion causes the cursor to move to a different line, the viewport re-centers.

### Edge Cases

| Edge Case | Best Practice (from iA Writer / Ulysses / Scrivener) |
|---|---|
| **Start of document** | The cursor stays at its natural top position. The document does NOT add phantom space above to force centering. Centering only activates once the cursor is far enough down that scrolling is possible. |
| **End of document** | Extra padding (typically 50vh) is added below the content so the last line CAN be scrolled to center. Without this, the last line is stuck at the bottom. |
| **Short document** (fits in viewport) | Typewriter scrolling is effectively a no-op. The cursor stays where it is since there is nothing to scroll. Some implementations (like Obsidian's) use `isOnlyMaintainTypewriterOffsetWhenReached` -- only center after the cursor has naturally scrolled past the offset threshold. |
| **Mouse clicks** | **Controversial.** Typora defaults to centering on click (configurable). Scrivener 3 does NOT re-center on click (fixes the Scrivener 2 problem of jarring jumps). Ulysses offers "Variable" mode where clicking freely moves the cursor but typing re-centers. **Best practice**: Do NOT center on mouse click. Only center during keyboard input. |
| **Text selection with mouse** | Never scroll during mouse-driven selection. |
| **Text selection with Shift+Arrow** | The selection endpoint should remain visible. Some implementations skip centering during shift-selection. |
| **Paste operations** | Center after paste completes (with a slight delay to let DOM settle). |
| **Undo/redo** | Re-center after undo/redo changes cursor position. |
| **Window resize** | Recalculate and re-center after resize. |
| **Focus changes** | Do not scroll when the editor loses or regains focus. Only center when the editor has focus AND the user is actively editing. |

### Scroll Animation

- **Smooth, not instant**: A linear interpolation (lerp) or ease-out animation prevents jarring jumps.
- **Retargetable**: If the user types faster than the animation completes, the animation target should be updated (retargeted) rather than queuing multiple animations.
- **Dead zone**: Small cursor movements (1-2px) should not trigger animation to prevent jitter.

### Configurable Offset Position

The fixed line position should be configurable:
- **Middle of editor** (default, 50%)
- **Top third** (~33%)
- **Bottom quarter** (~75%)
- Scrivener offers all three; Ulysses offers top/middle/bottom/variable.

---

## 2. Known Challenges with TipTap/ProseMirror

### Why This Is Hard

1. **ProseMirror's scroll system is built for "scroll-into-view", not "scroll-to-center"**: The built-in `scrollIntoView()` mechanism only ensures the cursor is visible within the viewport -- it does not center it. The `scrollThreshold` and `scrollMargin` props create buffer zones from the edges but cannot produce true center-locking behavior.

2. **`handleScrollToSelection` is all-or-nothing**: ProseMirror's `handleScrollToSelection` prop lets you return `true` to suppress the default scroll, or `false` to allow it. There is no built-in way to provide a *custom* scroll target -- you must implement the entire scroll calculation yourself.

3. **Coordinate timing issues**: `view.coordsAtPos()` returns viewport-relative coordinates, but after a state update the DOM may not have settled yet. Implementations must defer to `requestAnimationFrame()` to get accurate coordinates.

4. **`coordsAtPos` edge cases**: Known issues include inaccurate coordinates on first load, with widget decorations, with empty paragraphs, and at the end of the document (Issue #765). These require defensive coding with try/catch.

5. **No native smooth scroll support**: ProseMirror's `scrollIntoView` does not support `behavior: 'smooth'`. Confirmed by TipTap maintainers. Custom animation (via `requestAnimationFrame`) is required.

6. **Scroll container ambiguity**: ProseMirror can scroll multiple nested containers (editor div, parent container, window). The typewriter plugin must know which scroll container to target. This is especially tricky when the editor is inside a fixed-height panel vs. a full-page layout.

7. **Browser scroll anchoring conflicts**: Chrome's scroll anchoring feature (`overflow-anchor`) can fight with programmatic scroll adjustments, causing visible jitter. May need `overflow-anchor: none` on the scroll container.

8. **iOS-specific problems**: `scrollThreshold`/`scrollMargin` are unreliable on iOS. The soft keyboard changes the viewport, and `coordsAtPos` can return stale values. Mobile implementations need extra care.

### Common Pitfalls

- **Centering on every transaction**: Collaborative editing, programmatic updates, and decoration changes all trigger transactions. Centering on ALL of them causes unwanted jumps. Must filter to only user-initiated changes.
- **Conflicting with ProseMirror's native scroll**: If you don't suppress `handleScrollToSelection`, both your custom scroll and ProseMirror's default scroll will fire, causing double-scrolling or jitter.
- **Scroll-past-end**: Without extra padding at the bottom, the last line of the document can never be centered. This is the single most commonly missed requirement.
- **Animation frame leaks**: Not canceling `requestAnimationFrame` on plugin destroy causes memory leaks and ghost scrolling.

---

## 3. Existing Plugins and Extensions

### npm Packages

| Package | Status | Approach |
|---|---|---|
| [`prosemirror-scroll2cursor`](https://www.npmjs.com/package/prosemirror-scroll2cursor) | **Archived** (DO-NOT-USE org) | ProseMirror plugin with configurable `offsetTop`/`offsetBottom` thresholds. NOT true centering -- keeps cursor in a "comfortable zone". Uses a 50ms delay to prevent flicker during rapid key presses. |
| [`codemirror-typewriter-scrolling`](https://www.npmjs.com/package/codemirror-typewriter-scrolling) | Active (CM5 only) | CodeMirror 5 addon. `getCursor('anchor')`, `charCoords(cursor, 'local')`, calculates center offset. Skips when text is selected. Only triggers on `+input` and `+delete` origins. |
| [`@10play/tiptap-stagger`](https://www.npmjs.com/package/@10play/tiptap-stagger) | Active | Staggered *animation* effect (typewriter text appearance), NOT scroll behavior. Not relevant. |

### No dedicated TipTap typewriter-scroll npm package exists.

### GitHub Repositories

| Repository | Notes |
|---|---|
| [davisriedel/obsidian-typewriter-mode](https://github.com/davisriedel/obsidian-typewriter-mode) | **Best reference implementation.** CodeMirror 6 ViewPlugin. Full-featured: typewriter scroll, line highlighting, paragraph dimming, configurable offset (0-100%), "only maintain offset when reached" mode, Hemingway mode. Well-structured TypeScript codebase with `TypewriterOffsetCalculator` class. |
| [deathau/cm-typewriter-scroll-obsidian](https://github.com/deathau/cm-typewriter-scroll-obsidian) | Original Obsidian plugin. Simpler. Only scrolls on keyboard events (up/down/left/right + typing). Does NOT scroll on mouse click. Uses a zen mode opacity feature. |
| [azu/codemirror-typewriter-scrolling](https://github.com/azu/codemirror-typewriter-scrolling) | CodeMirror 5 reference. Minimal (5 files). Key insight: uses `getCursor('anchor')` not `head`, skips during selection, only fires on `+input`/`+delete` changes. |
| [WordPress/gutenberg PR #16460](https://github.com/WordPress/gutenberg/pull/16460) | Gutenberg's typewriter experience. Uses `requestAnimationFrame` continuous loop (not debounce). Direct DOM queries + ref-based caret tracking. Negligible perf impact (~54ms latency). Major iOS improvement. |

---

## 4. How Popular Editors Solve This

### iA Writer
- Typewriter mode is part of "Focus Mode"
- Cursor stays vertically centered while typing
- Clicking to edit elsewhere does NOT re-center (avoids jarring jumps)
- Conflict between click-selection and auto-centering is acknowledged in their docs
- Recommended to toggle Focus Mode off during heavy editing phases

### Ulysses
- Three independent features: **Highlight** (dim unfocused text), **Fixed Scrolling** (typewriter), **Mark Current Line** (grey tint)
- Fixed Scrolling offers four positions: top, middle, bottom, **variable**
- Variable mode: free cursor movement with mouse/arrows; line only fixes position when *typing begins*
- Separate configs for full-screen vs. windowed mode
- Keyboard shortcut: `Opt+Cmd+T`

### Typora
- Typewriter mode keeps caret in middle of window
- Preference: "Always keep caret in middle of screen when typewriter mode is enabled"
  - When checked: centers on click AND typing (default)
  - When unchecked: centers only during typing, not on click
- Focus Mode (separate toggle): dims all text except current line/block
- Known issue: screen jumping reported when both modes interact (Issue #1936)

### Scrivener
- Typewriter Scrolling keeps the editing line at a fixed screen position
- Default position: middle of editor (configurable to top third, bottom quarter on Mac)
- **Scrivener 3 key improvement**: When you click elsewhere to edit, it does NOT re-center. The fixed position adapts to where you clicked. Use `Cmd+J` to manually re-center.
- Separate toggles for Editor vs. Composition Mode (full-screen)
- Implementation detail: adds **phantom empty lines** at end of document to allow scroll-past-end

### VS Code
- No built-in typewriter mode, but has `editor.cursorSurroundingLines` setting
- Setting this to a very high value (e.g., 9999) approximates typewriter scrolling by keeping N lines above and below the cursor visible
- Extension ["Typewriter Scroll Mode"](https://marketplace.visualstudio.com/items?itemName=andyhuzhill.typewriterscrollmode) available on marketplace
- Zen mode (fullscreen + hidden UI) is separate from scroll behavior

### Obsidian
- Uses CodeMirror 6 under the hood
- Community plugin ["Typewriter Mode"](https://github.com/davisriedel/obsidian-typewriter-mode) is the most feature-rich:
  - Typewriter scrolling with configurable offset (0-100%)
  - "Only maintain offset when reached" option
  - Current line highlight
  - Paragraph dimming with configurable opacity
  - Keep N lines above/below mode (alternative to centering)
  - Hemingway mode (prevents editing previous text)
  - Cursor position restoration across files

---

## 5. Community Discussions

### TipTap GitHub

- **[Discussion #2264](https://github.com/ueberdosis/tiptap/discussions/2264)**: "How can I scroll the page to keep the input cursor in the middle of the screen?" -- Primary community thread. Two approaches shared:
  1. `editorProps: { scrollThreshold: 80, scrollMargin: 80 }` (insufficient for true centering)
  2. Manual calculation using `coordsAtPos` in `onUpdate` with `window.scrollTo`
- **[Discussion #2632](https://github.com/ueberdosis/tiptap/discussions/2632)**: Confirmed that smooth scroll (`behavior: 'smooth'`) is NOT supported by ProseMirror. Must implement custom animation.

### ProseMirror Discuss Forum

- **[Custom scroll function](https://discuss.prosemirror.net/t/custom-scroll-function/1157)**: Marijn (ProseMirror author) advises: "scrolling would be an imperative effect on the view object, not something you model with a step or a new transaction property." Recommends using `coordsAtPos` and building scroll logic yourself.
- **[scrollToSelection and resetScrollPos](https://discuss.prosemirror.net/t/prosemirror-view-scrolltoselection-and-resetscrollpos/909)**: Deep discussion of the internal scroll mechanism.
- **[Issue #848](https://github.com/ProseMirror/prosemirror/issues/848)**: `scrollThreshold` & `scrollMargin` were NOT respected during up/down arrow navigation. **Fixed in prosemirror-view 1.5.3** (keyboard selection changes now trigger `scrollIntoView`).
- **[Keep caret at distance from bottom](https://discuss.prosemirror.net/t/how-to-keep-the-caret-at-a-distance-from-the-bottom-of-the-window/3281)**: Confirmed that CSS margins/paddings are ineffective for this. `scrollMargin: 200, scrollThreshold: 200` is the recommended approach for buffer zones (but NOT centering).
- **[Scroll to specific node (center)](https://discuss.prosemirror.net/t/scroll-to-a-specific-node-in-the-editor-middle-of-the-parent-editor-container/6401)**: Recommends using native `element.scrollIntoView({ block: 'center', behavior: 'smooth' })` via `view.domAtPos()`.

---

## 6. ProseMirror's scrollIntoView Mechanism

### How It Works

1. When a transaction has `scrollIntoView()` called, ProseMirror sets an internal flag.
2. During the view update, if the flag is set, ProseMirror calls `scrollRectIntoView` (from `domcoords.ts`).
3. `scrollRectIntoView` walks up the DOM tree from the editor, finding all scrollable ancestors.
4. For each scrollable ancestor, it checks if the selection rectangle is within `scrollThreshold` pixels of an edge.
5. If so, it scrolls that ancestor to place the selection `scrollMargin` pixels from the edge.
6. The `handleScrollToSelection` prop is checked first -- if it returns `true`, the entire scroll mechanism is suppressed.

### API Surface

```typescript
// Transaction method -- flags this transaction for scroll
tr.scrollIntoView(): Transaction

// EditorProps
scrollThreshold?: number | { top, right, bottom, left }  // Default: 0
scrollMargin?: number | { top, right, bottom, left }       // Default: 5

// Override hook
handleScrollToSelection?(view: EditorView): boolean
// Return true = suppress default scroll
// Return false = let default scroll proceed

// Coordinate lookup
view.coordsAtPos(pos: number, side?: number): { left, right, top, bottom }
```

### Why scrollIntoView Is Insufficient for Typewriter Mode

1. **It ensures visibility, not centering.** Setting `scrollMargin` to `containerHeight / 2` would approximate centering, but the value must be dynamically calculated and ProseMirror treats it as a static prop.
2. **It uses instant scroll.** No animation support. ProseMirror maintainers confirmed this is by design.
3. **It fires for all scroll-flagged transactions**, not just user-initiated ones. Collaborative edits and programmatic changes would cause unwanted scroll jumps.
4. **Per-side configuration is limited.** You can set different margins for top/right/bottom/left, but you cannot make the margin dynamic based on cursor position.

---

## 7. CSS vs JavaScript Approaches

### CSS `scroll-padding` / `scroll-margin` Approach

**How it would work:**
```css
.editor-scroll-container {
  scroll-padding-top: 50vh;
  scroll-padding-bottom: 50vh;
}
```

**Verdict: Does NOT work for typewriter mode.** CSS `scroll-padding` only affects scroll snap behavior and programmatic `scrollIntoView` calls with `block: 'nearest'`. It does not affect continuous scrolling during typing. These CSS properties are designed for scroll-snap containers, not general scroll behavior.

### CSS `padding-bottom: 50vh` for Scroll-Past-End

**This IS required and effective:**
```css
.ProseMirror {
  padding-bottom: 50vh;
}
/* Or use an ::after pseudo-element: */
.ProseMirror::after {
  content: '';
  display: block;
  height: 50vh;
}
```

This is the CSS complement to the JavaScript scrolling logic. Without bottom padding, the last line of the document can never be scrolled to the center of the viewport.

### JavaScript-Based Approach (Required)

The only reliable approach for true typewriter mode in ProseMirror/TipTap:

1. **Suppress ProseMirror's default scroll** via `handleScrollToSelection() { return true; }`
2. **Calculate cursor position** via `view.coordsAtPos(selection.from)`
3. **Compute scroll delta**: `cursorY - (containerHeight / 2)`
4. **Animate scroll** via `requestAnimationFrame` with lerp/ease-out
5. **Filter events**: Only scroll on user-initiated changes (typing, deletion, arrow keys, undo/redo)
6. **Add bottom padding** via CSS for scroll-past-end

---

## 8. Recommended Implementation Architecture for TipTap

### Existing Implementation Analysis

This project already has a `TypewriterScrollExtension` at `/resources/js/extensions/TypewriterScrollExtension.ts`. Current approach:

- Uses `handleScrollToSelection() { return true; }` to suppress default scroll when enabled
- Uses `centerCursorInContainer()` with `coordsAtPos` for position calculation
- Lerp animation (0.18 speed) with dead zone (2px) and retargetable animation
- Listens to both `selectionchange` DOM events and ProseMirror `update()` lifecycle
- Accepts a `scrollContainerRef` (external scroll container) and `enabledRef` (toggle)

### Potential Improvements Based on Research

1. **Mouse click handling**: The current implementation centers on ALL selection changes including mouse clicks. Based on Scrivener 3 and Ulysses "Variable" mode research, consider only centering on keyboard-driven changes (typing, arrow keys, undo/redo) and not on mouse clicks. The Obsidian plugin distinguishes between user events by regex-matching transaction metadata.

2. **Scroll-past-end padding**: Ensure the editor container has `padding-bottom: 50vh` (or dynamically calculated to match the typewriter offset) when typewriter mode is enabled. Without this, the last line cannot be centered.

3. **"Only maintain offset when reached" mode**: From Obsidian's implementation -- don't force-center when the cursor is near the top of a short document. Only activate centering once the cursor has naturally scrolled past the offset threshold. This prevents the jarring effect of the first line being pushed to the middle of the screen.

4. **Configurable offset**: Allow the user to choose where the fixed line sits (top third, middle, bottom quarter) rather than hardcoding 50%.

5. **ResizeObserver**: The Obsidian plugin uses a ResizeObserver on the editor to recalculate on container resize. Currently not handled.

6. **Transaction filtering**: Instead of centering on every `selectionchange` + every `update`, inspect the ProseMirror transaction metadata to determine if the change was user-initiated. The Obsidian plugin checks for transaction user event annotations matching patterns like `input`, `delete`, `undo`, `redo`.

### Architecture Pattern (from research synthesis)

```
TipTap Extension
  |
  +-- handleScrollToSelection: return true (suppress default)
  |
  +-- ProseMirror Plugin view()
        |
        +-- update(view, prevState)
        |     |-- Check: is typewriter enabled?
        |     |-- Check: did selection or doc change?
        |     |-- Check: was change user-initiated? (not collab/programmatic)
        |     |-- requestAnimationFrame -> centerCursorInContainer()
        |
        +-- destroy()
              |-- Remove event listeners
              |-- Cancel animation frame

centerCursorInContainer(view, container, instant)
  |-- coordsAtPos(selection.from) -> cursor viewport Y
  |-- container.getBoundingClientRect() -> container top
  |-- cursorRelativeY = cursor.top - container.top
  |-- targetY = container.height * offset (e.g., 0.5)
  |-- delta = cursorRelativeY - targetY
  |-- Clamp to [0, maxScroll]
  |-- Animate with lerp via requestAnimationFrame

CSS Requirements:
  .ProseMirror { padding-bottom: 50vh; }  /* when typewriter enabled */
  .scroll-container { overflow-anchor: none; }  /* prevent Chrome jitter */
```

---

## Sources

### TipTap/ProseMirror Official
- [TipTap Discussion #2264 -- Cursor centering](https://github.com/ueberdosis/tiptap/discussions/2264)
- [TipTap Discussion #2632 -- Smooth scroll not supported](https://github.com/ueberdosis/tiptap/discussions/2632)
- [ProseMirror Reference Manual](https://prosemirror.net/docs/ref/)
- [ProseMirror Issue #848 -- scrollThreshold fix](https://github.com/ProseMirror/prosemirror/issues/848)
- [ProseMirror Discuss -- Custom scroll function](https://discuss.prosemirror.net/t/custom-scroll-function/1157)
- [ProseMirror Discuss -- Keep caret from bottom](https://discuss.prosemirror.net/t/how-to-keep-the-caret-at-a-distance-from-the-bottom-of-the-window/3281)
- [ProseMirror Discuss -- Scroll to center](https://discuss.prosemirror.net/t/scroll-to-a-specific-node-in-the-editor-middle-of-the-parent-editor-container/6401)
- [ProseMirror Discuss -- handleScrollToSelection issues](https://discuss.prosemirror.net/t/problem-with-scrollintoview-handler/2570)

### Plugins and Implementations
- [prosemirror-scroll2cursor (archived)](https://github.com/kongdivin/prosemirror-scroll2cursor)
- [codemirror-typewriter-scrolling](https://github.com/azu/codemirror-typewriter-scrolling)
- [Obsidian Typewriter Mode (davisriedel)](https://github.com/davisriedel/obsidian-typewriter-mode)
- [Obsidian Typewriter Scroll (deathau)](https://github.com/deathau/cm-typewriter-scroll-obsidian)
- [WordPress Gutenberg Typewriter PR #16460](https://github.com/WordPress/gutenberg/pull/16460)

### Editor Documentation
- [Ulysses Typewriter Mode](https://ghost-staging.ulysses.app/typewriter-mode/)
- [Typora Focus and Typewriter Mode](https://support.typora.io/Focus-and-Typewriter-Mode/)
- [Typora Issue #1936 -- More natural typewriter mode](https://github.com/typora/typora-issues/issues/1936)
- [Scrivener Typewriter Scrolling](https://scrivenerclasses.com/lesson/typewriter-scrolling/)
- [iA Writer Focus Mode](https://ia.net/writer/support/editor/focus-mode)
- [VS Code Typewriter Scroll Mode extension](https://marketplace.visualstudio.com/items?itemName=andyhuzhill.typewriterscrollmode)

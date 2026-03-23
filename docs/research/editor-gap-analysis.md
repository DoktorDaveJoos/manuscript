# Manuscript Editor — Gap Analysis vs. Best-in-Class

> Comparing Manuscript's current editor implementation against what the best novel-writing editors offer (Scrivener, Ulysses, iA Writer, Novelcrafter, Atticus, Dabble).

---

## What Manuscript Already Does Well

These are areas where Manuscript is **competitive or best-in-class**:

| Feature | Manuscript Status | Notes |
|---------|------------------|-------|
| **Book-like typography** | Excellent | EB Garamond default, justified text, auto-indentation (first para flush, others indented), hyphens:auto, 1.45 line-height, 660px max-width — this rivals iA Writer and Ulysses |
| **Font choice + size** | Good | 6+ serif fonts, 12-24px slider. Persisted in localStorage |
| **Typewriter scrolling** | Excellent | Custom LERP animation at 0.18 speed, 2px dead zone, RAF-based smooth scroll — well-implemented |
| **Focus/Zen mode** | Excellent | Full-screen, hides all chrome, WhisperChrome footer fades after 2.5s inactivity, Esc to exit |
| **Auto-save** | Excellent | 1500ms debounce, per-scene saves, AbortController for in-flight cancellation, flush-on-navigate, flush-on-unmount, status indicator (unsaved/saving/saved/error) |
| **Chapter/scene hierarchy** | Good | Storylines → Chapters → Scenes. Collapsible. Drag-and-drop scene reordering |
| **Scene management** | Excellent | Split at cursor (scene or chapter), cross-scene arrow navigation, scene registry, individual scene editors |
| **Version history** | Excellent | Manual snapshots, multiple sources tracked (original, AI revision, manual edit, normalization, beautify), restore, diff view with word-level highlighting, accept/reject pending AI revisions |
| **AI integration** | Strong | Chat drawer with story context, beautify (prose rewrite), normalize (grammar fix), pending version review workflow, BYOK-ready architecture |
| **Notes panel** | Good | Chapter notes with markdown blocks (checkboxes, bullets, headings, callouts), slash menu, auto-save |
| **Command palette** | Good | Cmd+P, search/filter, keyboard navigation |
| **Dark mode** | Yes | Full light/dark via CSS variables |
| **Export** | Good | EPUB, PDF with chapter selection, reading order, trim size, front/back matter |
| **Word count** | Good | Real-time per-scene, aggregated to chapter, displayed in editor bar, focus mode footer, sidebar |
| **Chapter status** | Good | Draft/Revised/Final badges |

**Bottom line**: Manuscript's core writing experience — typography, typewriter mode, focus mode, auto-save — is genuinely excellent and competitive with iA Writer and Ulysses. The version history with AI revision workflow is a unique differentiator no competitor matches.

---

## Critical Gaps — The "Non-Negotiables" Missing

These are features that **every serious writing editor has** and writers expect:

### 1. Find & Replace (Manuscript-Wide)
- **Status**: Not implemented
- **Impact**: CRITICAL — writers rename characters, fix repeated phrases, correct misspellings across 80,000+ word manuscripts
- **What best-in-class offers**: Scrivener searches across all documents in a project with regex support. Even basic editors like Google Docs have Ctrl+H
- **Recommendation**: Must search across ALL scenes in a chapter AND across ALL chapters in a book. Regex support is a differentiator

### 2. Undo/Redo in UI
- **Status**: TipTap's StarterKit includes history extension (so Cmd+Z likely works in browser), but no UI buttons or explicit shortcut mappings
- **Impact**: HIGH — writers expect visual undo/redo. Version history covers macro-level, but undo covers keystroke-level
- **Recommendation**: Verify Cmd+Z/Cmd+Shift+Z work. Add undo/redo to toolbar or command palette

### 3. Word Count Goals & Progress Tracking
- **Status**: Has word count display and "daily goal tracking" via session deltas, but no visible goal-setting UI, progress bars, or deadline tracking
- **Impact**: HIGH — Scrivener, Dabble, Ulysses, Atticus ALL have visual progress bars. Writers use daily word count targets as their primary motivational tool
- **Recommendation**: Per-session goal (e.g., "500 words today"), per-chapter goal, per-book goal with progress visualization. Writing streak tracking

### 4. Spell Check
- **Status**: Not implemented (relies on browser spellcheck)
- **Impact**: MEDIUM — browser spellcheck covers basics, but a dedicated writing app should own this experience
- **Recommendation**: Browser spellcheck is acceptable for now. Custom dictionary for character/place names would be a differentiator

---

## High-Impact Gaps — The "Differentiators"

These separate good editors from great ones:

### 5. Split View / Reference Pane
- **Status**: Not implemented (single editor visible at a time). Notes panel exists but only for current chapter notes
- **Impact**: HIGH — Scrivener's #1 loved feature after the Binder. Writers constantly need to reference character details, previous scenes, or research while writing
- **What best-in-class offers**: Scrivener has 4 reference mechanisms (Split Editor, Copyholders, Inspector Bookmarks, Quick Reference windows). Novelcrafter has a flyout character reference
- **Recommendation**: A quick-reference panel that can show any chapter, wiki entry, or character sheet alongside the current writing surface

### 6. Corkboard / Outline View
- **Status**: Not implemented. Sidebar shows chapter list only
- **Impact**: HIGH — This is Scrivener's second most-loved feature. Visual thinkers need to see their entire manuscript as index cards they can rearrange
- **What best-in-class offers**: Scrivener's corkboard (drag-and-drop index cards with synopses), outliner (spreadsheet view with metadata columns). Dabble's plot grid
- **Recommendation**: A corkboard view showing chapters as cards with synopsis/metadata, plus an outline view with sortable columns

### 7. Scene Synopses / Metadata
- **Status**: Scenes have title, content, word count, sort order — minimal metadata. No synopsis field, no POV field, no location field on scenes
- **Impact**: MEDIUM-HIGH — Scene-level metadata is what powers corkboard views, outline filtering, and manuscript overview. Without it, the organizational tools have no data to display
- **Recommendation**: Add synopsis (short text), POV character, location, and custom label fields to scenes

### 8. Manuscript-Level Writing Statistics
- **Status**: Word count per scene/chapter exists. Session tracking exists. No charts, graphs, trends, or analytics
- **Impact**: MEDIUM — Writers love to see "words per day over time" charts, most productive hours, streak tracking. It's motivational
- **What best-in-class offers**: Scrivener tracks session targets with progress bars. 4TheWords gamifies it. NaNoWriMo integration is common
- **Recommendation**: Writing statistics dashboard — daily word count chart, streak tracking, pace toward deadline

### 9. Inline Comments / Annotations
- **Status**: Not implemented
- **Impact**: MEDIUM — Essential for writers working with editors or beta readers. Also useful for self-annotation ("TODO: fix this dialogue")
- **What best-in-class offers**: Scrivener has inline annotations and linked comments. Google Docs has commenting
- **Recommendation**: Inline comments that can be toggled visible/hidden. Useful for self-editing workflow

### 10. Keyboard Shortcut Customization & Documentation
- **Status**: Has some shortcuts (Cmd+P, Cmd+B, Cmd+I, Cmd+Shift+Enter) but no shortcut reference, no customization, and some expected shortcuts are missing
- **Impact**: MEDIUM — Power users (serious novelists) live on keyboard shortcuts
- **Missing shortcuts**: Cmd+1/2/3 for heading levels, Cmd+] for indent, Cmd+[ for outdent, shortcuts for scene navigation
- **Recommendation**: Cmd+P → "Keyboard Shortcuts" to show all available shortcuts. Add shortcuts for all formatting options

---

## Nice-to-Have Gaps — Future Differentiation

### 11. The Codex / World Bible
- **Status**: Wiki feature exists (mentioned in sidebar navigation), but not integrated into the writing view
- **Impact**: MEDIUM — Novelcrafter's Codex is their killer feature. Having character/world data accessible while writing AND available to AI is powerful
- **Recommendation**: Integrate wiki entries as flyout references in the editor. Feed wiki data to AI for context-aware assistance

### 12. Visual Timeline
- **Status**: Timeline mentioned in chapter metadata (POV character, timeline label shown below title)
- **Impact**: MEDIUM — Only Plottr and Campfire have good timelines. Building one into the writing app would be a significant differentiator
- **Recommendation**: Future consideration. The metadata is there, the visualization isn't

### 13. Templates / Story Structure
- **Status**: Not implemented
- **Impact**: LOW-MEDIUM — Useful for new writers. Hero's Journey, Save the Cat beat sheets, three-act structure templates
- **Recommendation**: Could be a great onboarding feature for new projects

### 14. DOCX Export
- **Status**: Has EPUB and PDF, but DOCX not confirmed
- **Impact**: MEDIUM — Agents and publishers require Word format for manuscript submissions
- **Recommendation**: Verify DOCX export exists. If not, add it — it's a dealbreaker for traditionally-published authors

### 15. Reading Mode / Preview
- **Status**: Not implemented
- **Impact**: LOW-MEDIUM — Writers want to read their manuscript as a reader would, without editing UI
- **Recommendation**: A read-only view with clean formatting, page breaks between chapters

---

## Competitive Positioning Summary

```
                    WRITING EXPERIENCE    ORGANIZATION    AI INTEGRATION    FORMATTING/EXPORT
Scrivener           ★★★☆☆                ★★★★★           ☆☆☆☆☆             ★★★★★
Ulysses             ★★★★★                ★★★☆☆           ☆☆☆☆☆             ★★★☆☆
iA Writer           ★★★★★                ★★☆☆☆           ★★☆☆☆             ★★★☆☆
Novelcrafter        ★★★☆☆                ★★★★☆           ★★★★★             ★★☆☆☆
Atticus             ★★★☆☆                ★★★☆☆           ☆☆☆☆☆             ★★★★★
Dabble              ★★★★☆                ★★★★☆           ☆☆☆☆☆             ★★☆☆☆
────────────────────────────────────────────────────────────────────────────────────────
MANUSCRIPT          ★★★★★                ★★★☆☆           ★★★★☆             ★★★☆☆
```

**Manuscript's unique position**: It already has the **best-in-class writing experience** (matching iA Writer/Ulysses) combined with **strong AI integration** (matching/exceeding Novelcrafter). No competitor has both.

**The gap**: Organization (Scrivener territory) and formatting control (Scrivener/Atticus territory). Closing the organization gap with find & replace, word count goals, and a corkboard view would make Manuscript the most well-rounded writing tool available.

---

## Prioritized Roadmap Recommendation

### Phase 1: Close Critical Gaps (Non-Negotiable)
1. **Find & Replace** — manuscript-wide, cross-scene, cross-chapter
2. **Word count goals** — daily target, progress bar, visual feedback
3. **Undo/Redo verification** — ensure it works, add to toolbar/palette

### Phase 2: Differentiate (Competitive Edge)
4. **Split view / Quick Reference** — view any document alongside writing
5. **Scene metadata** — synopsis, POV, location fields
6. **Corkboard view** — visual chapter/scene overview with drag-and-drop
7. **Writing statistics** — daily chart, streak, pace tracking

### Phase 3: Polish & Extend
8. **Inline comments/annotations**
9. **DOCX export** (verify or add)
10. **Keyboard shortcut reference + customization**
11. **Wiki/Codex integration** in editor view
12. **Reading/preview mode**

---

## The Vision

> "Scrivener's organizational depth + iA Writer's writing beauty + Novelcrafter's AI intelligence — in a modern, native-feeling app."

Manuscript is already 70% of the way there. The writing surface is world-class. The AI revision workflow is unique. The critical missing pieces are **find & replace**, **word count goals**, and **organizational views** (corkboard, split reference). Closing those gaps would make it the most compelling novel-writing tool on the market.

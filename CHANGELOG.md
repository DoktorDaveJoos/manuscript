# Changelog

## v0.4.5 (2026-04-08)

### Features

- splitscreen editor refinements + multilingual spellcheck dictionaries (93eb903)
- wire sidebar for splitscreen — Cmd+click and context menu support (2c11a68)
- route editor through new pane-based EditorPage (527833f)
- add EditorPage pane manager as new editor entry point (c3545e3)
- extract ChapterPane component from ChapterShow (0932afd)
- add PaneEmptyState component for all-panes-closed state (b0ef1c2)
- add usePaneManager hook for splitscreen pane orchestration (f3fb60a)
- add useChapterData hook for fetch-based chapter loading (c3679e3)
- add JSON endpoint for chapter data (splitscreen support) (fb4d073)

### Fixes

- pass absolute URL to loading window in production (ac969b3)
- cross-platform dictionaries:copy for Windows builds (30d667d)
- sync notes panel content after switching panes (d3dcb74)
- refresh chapter data when pane regains focus (f82c0ed)
- prevent splitscreen panes from overlapping their close buttons (f569b63)
- remove unsupported JSON Schema constraints from AI agent schemas (f7a799f)
- restore ChapterController@show as redirect to preserve Wayfinder types (27f47a1)

### Other Changes

- update changelog for v0.4.4 (82594ca)
- enable OPcache in CLI + bundle fonts + reduce per-request queries (9e1b842)
- update changelog for v0.4.3 (696ad49)
- vendor-fallback nativephp/electron with patch layer (4c2127e)
- Merge pull request #98 from DoktorDaveJoos/fix/chapter-data-soft-refresh-on-focus (8e39b23)
- Merge pull request #97 from DoktorDaveJoos/worktree-agent-aa0717bd (7849615)
- Merge branch 'dev' into worktree-agent-aa0717bd (00a699e)
- Improve splitscreen editor: lift scenes state, remove save debounce, add i18n (b890b17)
- Add spellcheck context menu, command palette toggle, and i18n support (4dcde3f)
- simplify splitscreen code after review (bad2cfc)
- Replace typo-js spell checking with native Electron OS spellchecker (469d46c)
- add integration tests for splitscreen editor routing (2063f75)
- convert ChapterShow to thin redirect for backward compat (5cac268)
- Add wiki panel to editor for quick-reference while writing (61e0c16)
- Add real-time spell and grammar checking with typo-js and write-good (1739c2d)
- Fix invalid enum values in MarketingSeeder (ec98174)


## v0.4.4 (2026-04-08)

### Features

- splitscreen editor refinements + multilingual spellcheck dictionaries (93eb903)
- wire sidebar for splitscreen — Cmd+click and context menu support (2c11a68)
- route editor through new pane-based EditorPage (527833f)
- add EditorPage pane manager as new editor entry point (c3545e3)
- extract ChapterPane component from ChapterShow (0932afd)
- add PaneEmptyState component for all-panes-closed state (b0ef1c2)
- add usePaneManager hook for splitscreen pane orchestration (f3fb60a)
- add useChapterData hook for fetch-based chapter loading (c3679e3)
- add JSON endpoint for chapter data (splitscreen support) (fb4d073)

### Fixes

- cross-platform dictionaries:copy for Windows builds (30d667d)
- sync notes panel content after switching panes (d3dcb74)
- refresh chapter data when pane regains focus (f82c0ed)
- prevent splitscreen panes from overlapping their close buttons (f569b63)
- remove unsupported JSON Schema constraints from AI agent schemas (f7a799f)
- restore ChapterController@show as redirect to preserve Wayfinder types (27f47a1)

### Other Changes

- enable OPcache in CLI + bundle fonts + reduce per-request queries (9e1b842)
- update changelog for v0.4.3 (696ad49)
- vendor-fallback nativephp/electron with patch layer (4c2127e)
- Merge pull request #98 from DoktorDaveJoos/fix/chapter-data-soft-refresh-on-focus (8e39b23)
- Merge pull request #97 from DoktorDaveJoos/worktree-agent-aa0717bd (7849615)
- Merge branch 'dev' into worktree-agent-aa0717bd (00a699e)
- Improve splitscreen editor: lift scenes state, remove save debounce, add i18n (b890b17)
- Add spellcheck context menu, command palette toggle, and i18n support (4dcde3f)
- simplify splitscreen code after review (bad2cfc)
- Replace typo-js spell checking with native Electron OS spellchecker (469d46c)
- add integration tests for splitscreen editor routing (2063f75)
- convert ChapterShow to thin redirect for backward compat (5cac268)
- Add wiki panel to editor for quick-reference while writing (61e0c16)
- Add real-time spell and grammar checking with typo-js and write-good (1739c2d)
- Fix invalid enum values in MarketingSeeder (ec98174)


## v0.4.3 (2026-04-08)

### Features

- splitscreen editor refinements + multilingual spellcheck dictionaries (93eb903)
- wire sidebar for splitscreen — Cmd+click and context menu support (2c11a68)
- route editor through new pane-based EditorPage (527833f)
- add EditorPage pane manager as new editor entry point (c3545e3)
- extract ChapterPane component from ChapterShow (0932afd)
- add PaneEmptyState component for all-panes-closed state (b0ef1c2)
- add usePaneManager hook for splitscreen pane orchestration (f3fb60a)
- add useChapterData hook for fetch-based chapter loading (c3679e3)
- add JSON endpoint for chapter data (splitscreen support) (fb4d073)

### Fixes

- sync notes panel content after switching panes (d3dcb74)
- refresh chapter data when pane regains focus (f82c0ed)
- prevent splitscreen panes from overlapping their close buttons (f569b63)
- remove unsupported JSON Schema constraints from AI agent schemas (f7a799f)
- restore ChapterController@show as redirect to preserve Wayfinder types (27f47a1)

### Other Changes

- vendor-fallback nativephp/electron with patch layer (4c2127e)
- Merge pull request #98 from DoktorDaveJoos/fix/chapter-data-soft-refresh-on-focus (8e39b23)
- Merge pull request #97 from DoktorDaveJoos/worktree-agent-aa0717bd (7849615)
- Merge branch 'dev' into worktree-agent-aa0717bd (00a699e)
- Improve splitscreen editor: lift scenes state, remove save debounce, add i18n (b890b17)
- Add spellcheck context menu, command palette toggle, and i18n support (4dcde3f)
- simplify splitscreen code after review (bad2cfc)
- Replace typo-js spell checking with native Electron OS spellchecker (469d46c)
- add integration tests for splitscreen editor routing (2063f75)
- convert ChapterShow to thin redirect for backward compat (5cac268)
- Add wiki panel to editor for quick-reference while writing (61e0c16)
- Add real-time spell and grammar checking with typo-js and write-good (1739c2d)
- Fix invalid enum values in MarketingSeeder (ec98174)


## v0.4.2 (2026-04-01)

### Other Changes

- Merge remote-tracking branch 'origin/main' into dev (4885698)
- Add Windows build to publish workflow (441e7cb)
- Fix release script to let publish workflow handle release creation (d3080fc)


## v0.4.1 (2026-04-01)

### Features

- add EPUB import support (90f3848)
- add Unicode NFC normalization rule for imported text (9962e9d)
- add parse error logging and content warnings to import flow (fba0d10)
- add unsupported file feedback and client-side size check to DropZone (94094ac)
- add CJK-aware word counting via WordCount helper (fd72373)
- broaden chapter heading detection for multilingual and structural patterns (e4bedd0)

### Fixes

- detect Windows-1252 encoding for smart quotes in TXT imports (d465ab1)
- move DocxParserService to Parsers namespace and add mergeAdjacentTags whitespace handling (7810f9d)
- OdtParser blockquotes, nested sections, style reading, and tag merging (05bf7ca)
- require at least 3 non-space separator chars for scene break detection (7fd8e67)
- use CJK-aware WordCount in confirmImport and enforce max 20 files server-side (e28a340)
- harden editorial review controller and surface in-progress reviews (747310b)
- add cascade delete on chapter_id FK and composite index on editorial_reviews (772b55b)
- add scheduled cleanup for zombie editorial reviews (6382ab7)
- add empty-book short-circuit and upgrade finding key hash to xxh128 (eb5a7bf)
- rollback optimistic UI on non-2xx server responses in useToggleFinding (bbbceac)
- handle mixed-formatting docx imports (German styles, false positives, fragmented tags) (6784864)
- improve import page UX with upload feedback, file limits, and state preservation (3ce55d7)
- wrap confirmImport in DB transaction and recalculate word count (2ed1185)
- tighten scene break detection and preserve TXT single line breaks (1cc5a30)
- preserve preamble content before first chapter heading during import (5dacfc8)

### Other Changes

- Fix ESLint import order in useToggleFinding and import page (d56d63b)
- Refactor parsers, improve editorial review error handling (fdc9579)
- Merge pull request #88 from DoktorDaveJoos/feat/epub-parser (41ac963)
- Merge branch 'dev' into feat/epub-parser (3844c25)
- Merge pull request #85 from DoktorDaveJoos/fix/docx-namespace-and-merge-tags (5a15afb)
- Merge branch 'dev' into fix/docx-namespace-and-merge-tags (a04ca61)
- Merge pull request #87 from DoktorDaveJoos/fix/txt-parser-encoding-detection (786a2ca)
- Merge branch 'dev' into fix/txt-parser-encoding-detection (d4d6dad)
- Merge pull request #86 from DoktorDaveJoos/feat/nfc-normalization-rule (b55191e)
- Merge pull request #84 from DoktorDaveJoos/fix/odt-parser-improvements (4a1244a)
- Merge branch 'dev' into fix/odt-parser-improvements (ee8a3c0)
- Merge pull request #83 from DoktorDaveJoos/fix/scene-break-regex (8735b39)
- Merge pull request #82 from DoktorDaveJoos/fix/import-wordcount-and-file-limit (55a351a)
- Merge remote-tracking branch 'origin/dev' into dev (6630741)
- Merge pull request #81 from DoktorDaveJoos/worktree-agent-ac5eec6b (64455d4)
- Merge pull request #78 from DoktorDaveJoos/worktree-agent-ac2b31ee (9112e00)
- Merge pull request #80 from DoktorDaveJoos/worktree-agent-ae9637e4 (19289b1)
- Merge pull request #77 from DoktorDaveJoos/worktree-agent-ab9bfb51 (409ff46)
- Merge pull request #79 from DoktorDaveJoos/worktree-agent-a8e444ca (9afd886)
- Merge remote-tracking branch 'origin/worktree-agent-a6bbcf5b' into dev (e9c006d)
- Merge pull request #74 from DoktorDaveJoos/worktree-agent-a937e8c8 (5b9302f)
- Merge remote-tracking branch 'origin/worktree-agent-af61aa07' into dev (b4cd44b)
- Merge remote-tracking branch 'origin/worktree-agent-a6c48876' into dev (11b2471)
- Merge pull request #72 from DoktorDaveJoos/worktree-agent-a622e113 (fa11bde)
- Merge pull request #71 from DoktorDaveJoos/worktree-agent-ac34989c (ed2c0f0)
- Merge pull request #70 from DoktorDaveJoos/worktree-agent-a066afb8 (5904160)
- Merge pull request #69 from DoktorDaveJoos/worktree-agent-a3f083f2 (ef90a3f)


## v0.3.0 (2026-03-21)

### Features

- rewrite ExportPreview to render server-generated PDFs via pdf.js (5f52858)
- replace mPDF with Chromium-based PDF export via Blade template (4390c0f)
- sync export preview with PDF/EPUB output, add front/back matter pages (2aaf760)
- export preview overhaul, resizable panels, settings back-button origin tracking (f0ca3ad)
- migrate to Polar licensing, add export page, harden production build (3262274)
- add Acknowledgment and About the Author settings sections (84b036b)

### Fixes

- simplify review — deduplicate types, wire context menu, add scoping (fccf975)
- update PlotReadingOrderTest for chapters prop removal and finalize plot page (bb032d0)
- update remaining references to removed plot_point columns (7ab91cc)
- restore copyright injection and fix post-merge test failures (7f234df)
- align export Reading Order sidebar spacing and layout to match Pencil design (4f435b8)

### Other Changes

- fix(types): resolve TypeScript errors — Map polyfill, Format type, pdfjs render, ref types (ae65ca2)
- fix(lint): format ExportReadingOrder with Prettier (10d9a1d)
- fix(lint): resolve ESLint errors — ignore worktrees, fix imports and unused vars (899fab7)
- fix(i18n): update plot empty state title, implement ExportTemplate on ClassicTemplate (0b8915c)
- fix(i18n): add missing plot emptyState.select translation key (bf5295c)
- fix(i18n): add missing German and Spanish translations (800777f)
- feat(plot): redesign timeline cards — white bg, grip icons, beat layout, divider (f7bf3be)
- fix(plot): return Inertia-compatible redirects from controllers, fix recursive clearSelection (933ea96)
- simplify code after review — fix N+1, memoize, extract shared types (9fbb04d)
- fix(test): update PlotPageTest to expect chapters prop (802ee20)
- Merge remote-tracking branch 'origin/feat/auto-open-drawer-on-create' (c0e8f0f)
- Merge remote-tracking branch 'origin/feat/plot-character-linking' (912a141)
- Merge remote-tracking branch 'origin/fix/chapter-linking-beat-detail-panel' (d323b53)
- Merge remote-tracking branch 'origin/feat/plot-drag-and-drop' (1f73a15)
- ignore worktree directories (059fef3)
- Merge remote-tracking branch 'origin/feat/delete-act-with-confirmation' (8940e85)
- Merge remote-tracking branch 'origin/feat/plot-context-menus' (46ce321)
- Merge pull request #14 from DoktorDaveJoos/worktree-agent-a48bfc4e (86f72fb)
- Merge pull request #13 from DoktorDaveJoos/worktree-agent-ad9fd147 (220190c)
- feat(plot): auto-open drawer on create with detail panels for beats, plot points, and acts (0c94767)
- feat(plot): add character linking on plot points with searchable selector (1c27518)
- fix(plot): add searchable chapter linking to BeatDetailPanel (4fd2855)
- feat(plot): add drag-and-drop for beats and plot points (c899018)
- feat(plot): add act deletion with confirmation dialog and cascade delete (948723c)
- feat(plot): replace 3-dot menus with right-click context menus for acts and plot points (921e314)
- fix(plot): correct Create Chapter route in beat context menu (2fede30)
- feat(plot): add truncated description previews to cards and columns (5eec1b0)
- feat(plot): redesign wizard modal with per-template content, book examples, and full i18n (#12) (e1a3d3e)
- feat(plot): redesign empty state with book refs and act flows (#11) (881022c)
- update README with latest features — plot board, export formats, i18n (27178ea)
- feat(export): remove Dedication/AlsoBy, make template a select, upgrade Select component (37fca52)
- remove worktree directories from tracking (66dfcbb)
- Merge branch 'worktree-agent-a63c7d5c' (a33b207)
- feat(plot): rewrite plot page with ActColumn/Beat architecture, remove obsolete components (27fcdfa)
- Merge branch 'worktree-agent-a15686da' (e2a3aff)
- feat(plot): WU5 — add ActColumn, PlotPointSection, BeatCard timeline components (6cc5d5f)
- feat(plot): add BeatDetailPanel and BeatContextMenu components (88412e6)
- Merge branch 'worktree-agent-aa8a7c6a' (3de2bc6)
- feat(plot): WU4 — PlotController beats eager loading, TypeScript types, i18n keys, plot-constants (475da09)
- Merge branch 'worktree-agent-a3444dfd' (4758d5b)
- Merge branch 'worktree-agent-abccca92' (dd32d3c)
- Merge branch 'worktree-agent-ab708f7f' (e29740a)
- feat(plot): add Beat model, controller, migrations and full test suite (bd2cea1)
- remove deprecated columns from plot_points table (8f0dc1d)
- deduplicate CSS, type-safe preview format, gate non-visual previews (fe84988)
- feat(ai): remove AI-driven plot point extraction from ChapterAnalyzer (a9a6f09)
- modernize PDF template — Crimson Pro, tighter typography, no drop caps (bf0350b)
- polish PDF export — consolidate CSS, remove dead code, add mass market size (d476498)
- feat(export): switch PDF generation to mPDF, improve export UI and plot empty state (34caac3)
- feat(plot): add characters section to DetailPanel with role management (d7326e2)
- feat(plot): enrich PlotPointCard with description, word count, character initials (1514e3e)
- feat(plot): add Save the Cat and Story Circle templates (908b9c2)
- feat(plot): add CharacterPlotPointPivot and update PlotPoint type (ea1c100)
- feat(plot): sync characters on plot point update, eager-load in controller (ec6fe22)
- feat(plot): add characters<->plotPoints belongsToMany relationships (c04d097)
- feat(plot): add character_plot_point migration and CharacterPlotPointRole enum (50f231e)
- Merge pull request #10 from DoktorDaveJoos/worktree-agent-a20f647b (b060b15)
- merge: resolve Dialog.tsx conflict, keep conditional width (3c0acdd)
- Merge pull request #9 from DoktorDaveJoos/refactor/extract-shared-ui-components (7f7a7fb)
- merge: resolve FormField.tsx conflict, keep labelClassName variant (505c21d)
- Merge pull request #8 from DoktorDaveJoos/refactor/shared-dialog-formfield-sectionlabel (72ec177)
- Merge pull request #7 from DoktorDaveJoos/worktree-agent-a2d7ae2e (9f4eda8)
- Merge pull request #6 from DoktorDaveJoos/worktree-agent-a64b2c1a (63eda4e)
- extract shared Dialog component from 6 duplicated modal wrappers (ae5c977)
- extract SectionLabel, FormField, PanelHeader into shared UI components (1d35a22)
- Merge pull request #5 from DoktorDaveJoos/feature/chromium-pdf-unit5-tests (009d326)
- Merge pull request #4 from DoktorDaveJoos/feature/chromium-pdf-unit4-cleanup (5dc938f)
- Merge pull request #3 from DoktorDaveJoos/feature/chromium-pdf-unit3-frontend (2dfac1e)
- Merge pull request #2 from DoktorDaveJoos/feature/chromium-pdf-unit2-blade-exporter (2b8fc2b)
- merge: resolve FontService conflict (keep Unit 1 docstring) (48870eb)
- extract Dialog, FormField, SectionLabel components; update onboarding/wiki consumers (fd1c15a)
- Merge branch 'main' of https://github.com/DoktorDaveJoos/manuscript (20207ae)
- extract SectionLabel, Checkbox, ToggleRow into shared UI components (85f186c)
- Merge pull request #1 from DoktorDaveJoos/feature/chromium-pdf-unit1-services (182fe5b)
- extract shared ContextMenu component from 4 duplicated menus (effe457)
- update PDF export tests for Chromium-based System::printToPDF (6b14c74)
- remove dead usePreviewPages module (994e7ff)
- extract static methods from export services for Chromium PDF pipeline (3fb6ae8)
- add design spec for Chromium-based PDF export and preview (93686f3)
- remove unused handleToggleAll callback from export page (7a2a46c)


## v0.2.2 (2026-03-17)

### Features

- add release script with quality gate and changelog generation (0b28671)

### Fixes

- resolve TypeScript type errors across frontend components (bf09749)
- resolve Pint lint violations in CharacterFactory and ExampleTest (452c245)
- ignore untracked files in release pre-flight check (0146ba1)
- force-reload scenes in content hash and resolve EmbeddingService via container (02eb950)

### Other Changes

- apply Prettier formatting to AiChatDrawer (b687eaf)
- refine dark mode theme, add plot colors, and replace AI chat loading dots with spinner (3208667)
- apply Prettier formatting across codebase (44a6242)

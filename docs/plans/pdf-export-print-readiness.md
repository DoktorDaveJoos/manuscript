# PDF Export — Print-Readiness Audit & Roadmap

*Audit date: 2026-06-11. Code state: `dev` @ 144d047e. All 182 export tests passing.*

Two inputs: a full code audit of the export pipeline (mPDF 8.3.1), and provider research
covering Amazon KDP, IngramSpark/Lightning Source, Lulu, Blurb, BoD, epubli, tredition,
and CPI (offset comparison). Source links at the bottom.

---

## 1. Verdict in one paragraph

The rendering pipeline is in genuinely good shape: preview/export geometry parity is
guaranteed by construction (`PdfExporter::resolveGeometry()` feeds both the mPDF
constructor and the `@page` CSS) and guarded by tests that inspect real PDF bytes
(`pdfinfo` page counts, CMYK ink operators, `pdftotext` hyphenation/folio checks).
Margins mirror correctly per recto/verso, fonts are embedded TTFs, folio conventions are
professional. What is NOT yet true is "submit to any printer and pass preflight": the
bleed model is wrong for KDP/IngramSpark (the two biggest POD targets), the bleed value
can't be set to the 0.125″ they require, trim presets are rounded to whole millimetres,
and there is no PDF/X / TrimBox / grayscale story.

## 2. What is already right (do not regress)

| Area | Implementation | Guarded by |
|---|---|---|
| Preview ↔ export parity | `resolveGeometry()` is the single source for sheet + margins; `@page` CSS mirrors it | `PdfPrintGeometryTest` ("@page CSS uses the same bleed-adjusted geometry") |
| Page count invariant under bleed | margins shift by bleed so the text block never reflows | `PdfPrintGeometryTest` ("bleed does not change the page count") |
| Mirrored margins | `@page :left` / `:right` swap gutter/outer (`export/pdf.blade.php:24-30`) | — |
| CMYK black text | `restrictColorSpace: 3` + `body { color: cmyk(0,0,0,100) }` | `PdfCmykTest` (asserts `0 0 0 1 k` operators, no RGB) |
| Hyphenation | `SHYlang` from book locale, en/de/es+ patterns; `SHYcharmin ≤ 4` pinned (5 silently disables) | `PdfPageLayoutTest` (German compound breaks verified in bytes) |
| Folio conventions | front matter unnumbered, count opens at prologue, runs through epilogue | `PdfPageLayoutTest` |
| Blank-page regressions | scene-break `page-break-*: avoid` bug fixed | `PdfPageLayoutTest` (template CSS guard + long-book byte check) |
| Fonts | TTFs shipped in `resources/fonts/`, registered per pairing, mPDF embeds/subsets | `FontService` + availability check |

## 3. Gaps, by priority

### P0 — wrong output for the two biggest POD printers

**3.1 Bleed topology: all-4-edges only.**
`resolveGeometry()` grows the sheet by `2×bleed` in BOTH dimensions and pads every
margin (`PdfExporter.php:152-161`) — bleed at the bind edge. That matches Lulu, epubli,
tredition (all-4 model). KDP and IngramSpark *forbid* bleed on the bind edge: page size
must be `trim + 1×bleed` wide and `trim + 2×bleed` tall, with the bleed on the outer
edge — which alternates left/right between verso and recto. Today a "6×9 + 3 mm bleed"
export is 158×235 mm; KDP expects 155.6×235 mm (6.125×9.25″) and will misread the trim
size or reject. Fix: a per-edge bleed mode (`all` | `outer`), where `outer` adds bleed to
one width edge and mirrors it via the existing `:left`/`:right` margin machinery.

**3.2 Bleed value granularity.**
`NumberInput step={0.5}` (`ExportSettings.tsx:287`) makes the single most-required value
on the planet — 0.125″ = **3.175 mm** (KDP, IngramSpark, Lulu, Blurb) — impossible to
enter. BoD and tredition need **5 mm**; epubli 3 mm. Fix: replace free input with presets
**None / 3 mm / 3.175 mm (0.125″) / 5 mm** + custom (step 0.001), and keep validation
`0–25`.

**3.3 Trim presets rounded to whole mm.**
`TrimSize::dimensions()` stores inch sizes rounded: 6×9″ is 152.4×228.6 mm, enum says
152×229; 5.5×8.5″ is 139.7×215.9, enum says 140×216 — every imperial preset is off by up
to 0.5 mm. IngramSpark prints files as-sent (0.4 mm shift on their template); KDP's
preflight matches page size against the chosen trim with a tight tolerance. Fix: store
exact float conversions (and emit exact sizes in `@page`/constructor — they already flow
through as floats).

### P1 — required by at least one major provider, cheap to add

**3.4 Grayscale interior mode.** IngramSpark requires B&W interiors to be *grayscale,
no ICC, no spot* (an ICC profile silently turns 100K text grey). mPDF supports it today:
`restrictColorSpace: 1`. Add a three-way color option: **RGB (default) / Grayscale (B&W
print) / CMYK** replacing the current boolean.

**3.5 Gutter not page-count-aware.** Margins are static per trim size. Against the
published tables: KDP needs 0.875″ (22.2 mm) gutter at 701–828 pp — UsTrade's 22 mm just
misses; MassMarket's 16 mm fails KDP above 500 pp (needs 19 mm); Lulu recommends 1″
(25.4 mm) total inside margin already at 151–400 pp. Fix: compute a gutter floor from the
(estimated) page count — the exporter knows the real count post-render, so a two-pass or
"export, then warn" approach works — with a manual override.

**3.6 `"mpdf/mpdf": "*"` in composer.json.** A wildcard on the rendering engine whose
version-specific quirks are load-bearing (the `SHYcharmin` comment documents one). Pin to
`^8.3`.

### P2 — needed for "dedicated" print export, larger effort

**3.7 No PDF/X conformance, no TrimBox/BleedBox.** IngramSpark *requires* PDF/X-1a or
X-3; Blurb requires X-3; tredition covers X-3; CPI (offset) wants X-4 with
TrimBox = trim. mPDF cannot emit PDF/X or page boxes — this is the engine ceiling. Note
the planned Chromium replacement (`docs/superpowers/specs/2026-03-19-chromium-pdf-export-design.md`)
does NOT solve this: Chromium printToPDF is RGB-only, no PDF/X, no boxes — for print it
is *worse* than mPDF. Realistic options: (a) post-process with Ghostscript
(`-dPDFX` + output intent) when installed; (b) write TrimBox/BleedBox/MediaBox into the
finished PDF ourselves (simple dictionary entries; we already know the geometry); (c)
document "PDF/X on request" as a limitation. (b) is small and helps everywhere; do it
first.

**3.8 Spine width calculator.** `spine_width` is a bare manual field
(`CoverCreatorDialog.tsx:352`). The two providers with public formulas could be computed
from final page count: KDP `pages × 0.002252″` (white) / `× 0.0025″` (cream) /
`× 0.002347″` (premium color); Lulu `pages/444 + 0.06″`. Everyone else (BoD, epubli,
tredition, IngramSpark) only exposes spine width via their own calculator — so manual
entry must stay, but add a "calculate for: KDP white/cream/…, Lulu" helper seeded with
the book's last exported page count.

**3.9 Spine-text and cover-wrap rules.** No gate on spine text below the provider
minimums (KDP ≥ 80 pp, IngramSpark ≥ 48 pp, Lulu/Blurb/BoD ≥ 80 pp); no hardcover wrap
allowance (KDP/IS case laminate 0.625″, BoD/CPI 17 mm Einschlag + 8 mm hinge clearance);
no barcode reserve zone (IngramSpark: mandatory 100K barcode or 1.75×1″ blank area).
Add as warnings/options in the cover creator.

### P3 — polish

- **Inch display/input.** Everything is mm-only; KDP users think in 0.125″ steps. Show
  both units (presets already have dual labels — extend to bleed/custom size).
- **Custom-trim envelope warnings.** 50–500 mm validation allows unprintable sizes; warn
  outside the KDP (4–8.5 × 6–11.69″) / IS / BoD (108×170–216×297 mm) envelopes.
- **Page-count parity.** IngramSpark stores even page counts, tredition pads to ÷4,
  Blurb needs ÷6 for 5×8/6×9. Providers auto-pad, but padding ourselves keeps the spine
  width honest. Low risk to defer.
- **PDF metadata.** `SetTitle`/`SetAuthor` are never called — cosmetic but free.
- **`include_cover` in the interior.** KDP rejects interiors that contain the cover.
  Default it off (or warn) when the user is exporting a print PDF rather than a preview.
- **Image DPI checks.** Moot today (chapter images aren't exported; only the cover
  image), but becomes P1 if chapter images ever land. Cover uploads should check ≥300 DPI
  at final size now.

## 4. What differs most across providers (must stay adjustable, never hardcoded)

1. Bleed **amount** (3 / 3.175 / 5 mm) and **topology** (outer-3 vs all-4) — the largest divergence.
2. PDF conformance target (plain → X-1a → X-3 → X-4); X-1a forbids what X-4 expects.
3. Color pipeline (IS: grayscale/CMYK no-ICC; Lulu/BoD: prefer RGB; CPI: FOGRA intents; TAC 240–300%).
4. Gutter-vs-page-count tables (each provider has its own curve).
5. Spine factors (only KDP + Lulu public; others template-only → manual override mandatory).
6. Page-count divisibility (even / ÷4 / ÷6 / none) and min/max (18–1200 depending on paper).

## 5. Suggested implementation order

1. **P0 batch** (one PR, mostly `resolveGeometry()` + enum + UI): exact trim floats,
   bleed presets incl. 3.175, per-edge bleed mode. Extend `PdfPrintGeometryTest` for the
   outer-3 model (sheet = trim+1×bleed wide, bleed edge alternates).
2. **Color mode** (small PR): RGB/Grayscale/CMYK select; `restrictColorSpace 1|3`;
   extend `PdfCmykTest` with a grayscale assertion (`g`/`G` operators only).
3. **Pin mPDF** `^8.3` (one-liner, with `composer.lock` already at 8.3.1).
4. **TrimBox/BleedBox post-write** + PDF metadata.
5. **Gutter-by-page-count** warnings + spine calculator (needs exported page count
   surfaced from `Mpdf->page`).
6. **Cover rules** (spine-text gates, wrap allowances, barcode zone) in the cover creator.
7. Ghostscript-based PDF/X output as an optional "strict preflight" path — only if real
   user demand for IngramSpark/Blurb materialises.

## 6. Provider requirement quick-reference

| | KDP | IngramSpark | Lulu | Blurb | BoD | epubli | tredition |
|---|---|---|---|---|---|---|---|
| Bleed | 3.2 mm, **outer 3** | 3 mm, **outer 3** | 3.175 mm, all 4 | 3.175 mm, all 4 | **5 mm** | 3 mm, all 4 | **5 mm**, all 4 |
| Custom trim | yes (4–8.5×6–11.69″) | yes | no | no | yes (publisher) | no | yes (≤A4) |
| PDF/X | no | **X-1a/X-3 required** | no | **X-3 required** | X-3 recommended | no | PDF/A interior, X-3 cover |
| Interior color | any (ICC stripped) | grayscale/CMYK, **no ICC** | sRGB or CMYK | 100K text | **RGB recommended** | RGB/CMYK | CMYK |
| Spine text min | >79 pp | ≥48 pp | >80 pp | >80 pp (SC) | ≥80 pp / 5 mm | — | — |
| Page parity | none | even | none | even, ÷6 (5×8/6×9) | none | ÷4/6/8/12 (auto) | ÷4 (auto) |
| Pages | 24–828 | 18–1200 | 32+ | 24–480 | 24–700 | 36–980 | — |

Common preflight rejections across providers: unembedded fonts, bleed on the bind edge /
bleed not reaching the cut, page size ≠ trim or trim+bleed, crop/registration marks,
spreads, ICC/spot color in B&W interiors, <300 DPI images, spine text under the page
minimum, encrypted PDFs, cover not matching the provider's calculator size.

### Sources
- KDP: [submission guidelines](https://kdp.amazon.com/en_US/help/topic/G201857950), [trim/bleed/margins](https://kdp.amazon.com/en_US/help/topic/GVBQ3CMEQW3W2VL6), [cover](https://kdp.amazon.com/en_US/help/topic/G201953020), [common issues](https://kdp.amazon.com/en_US/help/topic/G201834260)
- IngramSpark: [File Creation Guide (PDF)](https://www.ingramspark.com/hubfs/downloads/file-creation-guide.pdf)
- Lulu: [Book Creation Guide (PDF)](https://assets.lulu.com/media/guides/en/lulu-book-creation-guide.pdf), [PDF settings](https://help.lulu.com/en/support/solutions/articles/64000255519)
- Blurb: [booksize calculator](https://www.blurb.com/make/pdf_to_book/booksize_calculator) (PDF/X-3 + spine facts third-party-relayed; support site bot-blocked)
- BoD: [PDF FAQ](https://www.bod.de/hilfe/faq/Was-muss-ich-beim-Erzeugen-einer-PDF-Datei-beachten/0e1fcc934821486eb8c9162ae3ab65f2/a32715559848487d9ab4302f6c66cb95), [Grafiken & Farbmanagement](https://www.bod.de/bodfiles/GLOBAL-Storage/documents/help-documents/bod-grafiken-und-farbmanagement.pdf)
- epubli: [Beschnitt FAQ](https://epubli.zendesk.com/hc/de/articles/360004249992) (numbers via indexed copies)
- tredition: [Innenteil](https://tredition.com/kb/innenteil-fuer-den-druck-vorbereiten/), [Cover](https://tredition.com/kb/cover-vorbereiten/)
- CPI: [Datenanlieferung](https://cpi-print.de/de/service/datenanlieferung/)

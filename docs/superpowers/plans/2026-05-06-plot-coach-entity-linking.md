# Plot Coach Solid Entity Linking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the plot coach reliably populate `character_ids` and `wiki_entry_ids` on every chapter it proposes; auto-include the POV character in the `character_chapter` pivot at persist time.

**Architecture:** A new `BeatEntityScanner` utility scans concatenated beat descriptions for known book-entity names with Unicode word-boundary matching. A shared trait `ValidatesChapterEntityLinks` runs the scanner from `ProposeChapterPlan` and `ProposeBatch` (for `type: chapter` writes) — when entities are referenced in beats but the corresponding ids list is empty, the tool returns a structured rejection so the agent retries. Separately, `PlotCoachBatchService::writeChapter` / `::updateChapter` `syncWithoutDetaching` the POV character into the `character_chapter` pivot (with `role => 'protagonist'`) on persist. Agent instructions in `PlotCoachAgent` are rewritten to reflect the new contract.

**Tech Stack:** PHP 8.4 · Laravel 13 · Laravel AI SDK (Tools, Agents) · Pest 4 · SQLite (default + `database/nativephp.sqlite`).

**Spec:** [`docs/superpowers/specs/2026-05-06-plot-coach-entity-linking-design.md`](../specs/2026-05-06-plot-coach-entity-linking-design.md)

---

## File Structure

**New:**
- `app/Ai/Support/BeatEntityScanner.php` — pure utility, scans beat-description text for entity-name matches.
- `app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php` — trait used by `ProposeChapterPlan` and `ProposeBatch`. Wraps the scanner, returns a rejection string or `null`.
- `tests/Unit/Ai/Support/BeatEntityScannerTest.php`
- `tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php`
- `tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php`
- `tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php`

**Modified:**
- `app/Ai/Tools/Plot/ProposeChapterPlan.php` — add validation call before `persistProposal`; update `description()` text.
- `app/Ai/Tools/Plot/ProposeBatch.php` — add validation call for `type: chapter` writes before `persistProposal`; update `description()` text.
- `app/Ai/Agents/PlotCoachAgent.php` — rewrite the chapter-proposal guidance block in `refinementGuidance()` (delete the misleading POV line, replace with per-chapter checklist, add a fully-populated example).
- `app/Services/PlotCoachBatchService.php` — in `writeChapter()` and `updateChapter()`, after pivot sync, `syncWithoutDetaching` the POV character with `role => 'protagonist'`.

---

## Task 1: BeatEntityScanner utility (unit-tested via TDD)

**Files:**
- Create: `app/Ai/Support/BeatEntityScanner.php`
- Test: `tests/Unit/Ai/Support/BeatEntityScannerTest.php`

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/Ai/Support/BeatEntityScannerTest.php`:

```php
<?php

use App\Ai\Support\BeatEntityScanner;

beforeEach(function () {
    $this->scanner = new BeatEntityScanner;
});

it('returns matches with case-insensitive word-boundary semantics', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Maja boards the Voyager probe.', 'John watches from afar.'],
        entities: [
            ['id' => 1, 'name' => 'Maja'],
            ['id' => 2, 'name' => 'John'],
            ['id' => 3, 'name' => 'Voyager'],
        ],
    );

    expect($matches)->toHaveCount(3);
    expect(collect($matches)->pluck('id')->all())->toEqualCanonicalizing([1, 2, 3]);
});

it('does not match a name embedded inside a longer word', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Johnson arrives at the dock.'],
        entities: [['id' => 1, 'name' => 'John']],
    );

    expect($matches)->toBeEmpty();
});

it('handles unicode word boundaries (German umlauts, compound words)', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Der Apparat trifft auf die Gravitationslinse.'],
        entities: [
            ['id' => 1, 'name' => 'Apparat'],
            ['id' => 2, 'name' => 'Voyager'],
            ['id' => 3, 'name' => 'Gravitationslinse'],
        ],
    );

    expect(collect($matches)->pluck('id')->all())->toEqualCanonicalizing([1, 3]);
});

it('skips entities whose name is shorter than three characters', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['AI chats with Z.', 'Maja arrives.'],
        entities: [
            ['id' => 1, 'name' => 'AI'],
            ['id' => 2, 'name' => 'Z'],
            ['id' => 3, 'name' => 'Maja'],
        ],
    );

    expect(collect($matches)->pluck('id')->all())->toEqual([3]);
});

it('returns empty for empty inputs', function () {
    expect($this->scanner->findReferenced([], []))->toBeEmpty();
    expect($this->scanner->findReferenced(['Maja arrives.'], []))->toBeEmpty();
    expect($this->scanner->findReferenced([], [['id' => 1, 'name' => 'Maja']]))->toBeEmpty();
});

it('records which beat indices each entity was found in', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: [
            'Maja arrives at the lab.',
            'John reviews the file.',
            'Maja briefs John.',
        ],
        entities: [
            ['id' => 1, 'name' => 'Maja'],
            ['id' => 2, 'name' => 'John'],
        ],
    );

    $byId = collect($matches)->keyBy('id');
    expect($byId[1]['beats'])->toEqualCanonicalizing([0, 2]);
    expect($byId[2]['beats'])->toEqualCanonicalizing([1, 2]);
});

it('dedupes within the same beat (one entity, one beat = one entry per beat)', function () {
    $matches = $this->scanner->findReferenced(
        beatDescriptions: ['Maja meets Maja in the mirror. Maja smiles.'],
        entities: [['id' => 1, 'name' => 'Maja']],
    );

    expect($matches)->toHaveCount(1);
    expect($matches[0]['beats'])->toEqual([0]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Ai/Support/BeatEntityScannerTest.php`
Expected: All seven tests fail with `Class "App\Ai\Support\BeatEntityScanner" not found`.

- [ ] **Step 3: Implement BeatEntityScanner**

Create `app/Ai/Support/BeatEntityScanner.php`:

```php
<?php

namespace App\Ai\Support;

/**
 * Scans beat-description text for references to known book entities by name.
 *
 * Pure, stateless. Used by ProposeChapterPlan / ProposeBatch validation to
 * detect when a chapter's beats reference characters or wiki entries that
 * the agent's proposal failed to declare.
 */
class BeatEntityScanner
{
    private const MIN_NAME_LENGTH = 3;

    /**
     * @param  list<string>  $beatDescriptions  text of each beat, in order
     * @param  list<array{id: int, name: string}>  $entities  candidate entities
     * @return list<array{id: int, name: string, beats: list<int>}>  matches with beat indices
     */
    public function findReferenced(array $beatDescriptions, array $entities): array
    {
        if ($beatDescriptions === [] || $entities === []) {
            return [];
        }

        $matches = [];

        foreach ($entities as $entity) {
            $name = trim((string) ($entity['name'] ?? ''));

            if (mb_strlen($name) < self::MIN_NAME_LENGTH) {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu';
            $hitBeats = [];

            foreach ($beatDescriptions as $index => $description) {
                if (preg_match($pattern, (string) $description) === 1) {
                    $hitBeats[] = $index;
                }
            }

            if ($hitBeats !== []) {
                $matches[] = [
                    'id' => (int) $entity['id'],
                    'name' => $name,
                    'beats' => $hitBeats,
                ];
            }
        }

        return $matches;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Ai/Support/BeatEntityScannerTest.php`
Expected: All seven tests pass.

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Support/BeatEntityScanner.php tests/Unit/Ai/Support/BeatEntityScannerTest.php
git commit -m "feat(plot-coach): add BeatEntityScanner utility for entity-name matching"
```

---

## Task 2: ValidatesChapterEntityLinks trait

**Files:**
- Create: `app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php`

This trait is exercised through Task 3 and Task 4 feature tests; it has no standalone test (the trait pattern in this repo follows `CoercesBookId` and `DecodesJsonPayload`, which are also tested transitively).

- [ ] **Step 1: Implement the trait**

Create `app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php`:

```php
<?php

namespace App\Ai\Tools\Plot\Concerns;

use App\Ai\Support\BeatEntityScanner;
use App\Models\Beat;
use App\Models\Character;
use App\Models\WikiEntry;

/**
 * Validate that a proposed chapter's character_ids / wiki_entry_ids are
 * non-empty whenever the chapter's beats reference known book entities.
 *
 * Existence-check only: the agent picks which specific entities to include;
 * this validator just ensures *something* was attempted when beats reference
 * book entities. False positives (agent disagrees with a regex hit) are fine
 * — agent supplies its own non-empty list and validation passes.
 */
trait ValidatesChapterEntityLinks
{
    /**
     * @param  list<array{title: string, beat_ids?: list<int>, character_ids?: list<int>, wiki_entry_ids?: list<int>}>  $chapters
     * @return string|null  null if all valid; else a multi-chapter rejection message
     */
    protected function validateChapterEntityLinks(int $bookId, array $chapters): ?string
    {
        if ($chapters === []) {
            return null;
        }

        $allBeatIds = [];
        foreach ($chapters as $chapter) {
            foreach ($chapter['beat_ids'] ?? [] as $id) {
                $allBeatIds[] = (int) $id;
            }
        }
        $allBeatIds = array_values(array_unique($allBeatIds));

        if ($allBeatIds === []) {
            return null;
        }

        $beatRows = Beat::query()
            ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
            ->whereIn('beats.id', $allBeatIds)
            ->where('plot_points.book_id', $bookId)
            ->get(['beats.id', 'beats.title', 'beats.description'])
            ->keyBy('id');

        if ($beatRows->isEmpty()) {
            return null;
        }

        $characters = Character::query()
            ->where('book_id', $bookId)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();

        $wikiEntries = WikiEntry::query()
            ->where('book_id', $bookId)
            ->get(['id', 'name'])
            ->map(fn ($w) => ['id' => (int) $w->id, 'name' => (string) $w->name])
            ->all();

        $scanner = new BeatEntityScanner;
        $errors = [];

        foreach ($chapters as $index => $chapter) {
            $beatIds = $chapter['beat_ids'] ?? [];
            $beatDescriptions = [];
            $beatTitlesByLocalIndex = [];

            foreach ($beatIds as $localIndex => $bid) {
                $row = $beatRows->get((int) $bid);
                $beatDescriptions[] = $row?->description ?? '';
                $beatTitlesByLocalIndex[$localIndex] = $row?->title ?? "beat #{$bid}";
            }

            $referencedChars = $scanner->findReferenced($beatDescriptions, $characters);
            $referencedWiki = $scanner->findReferenced($beatDescriptions, $wikiEntries);

            $charsListEmpty = empty($chapter['character_ids']);
            $wikiListEmpty = empty($chapter['wiki_entry_ids']);

            if ($referencedChars !== [] && $charsListEmpty) {
                $errors[] = $this->renderChapterError(
                    title: (string) $chapter['title'],
                    index: (int) $index,
                    field: 'character_ids',
                    matches: $referencedChars,
                    beatTitles: $beatTitlesByLocalIndex,
                );
            }

            if ($referencedWiki !== [] && $wikiListEmpty) {
                $errors[] = $this->renderChapterError(
                    title: (string) $chapter['title'],
                    index: (int) $index,
                    field: 'wiki_entry_ids',
                    matches: $referencedWiki,
                    beatTitles: $beatTitlesByLocalIndex,
                );
            }
        }

        if ($errors === []) {
            return null;
        }

        $header = "Chapter entity links missing — proposal rejected.\n\n";
        $footer = "\n\nRetry with the referenced entities included in `character_ids` / `wiki_entry_ids`. If a specific match is incidental (e.g. mentioned only in dialogue about a different scene), you may omit that id — but the lists cannot be empty when matches exist.";

        return $header.implode("\n\n", $errors).$footer;
    }

    /**
     * @param  list<array{id: int, name: string, beats: list<int>}>  $matches
     * @param  array<int, string>  $beatTitles  map of local beat index → beat title
     */
    private function renderChapterError(string $title, int $index, string $field, array $matches, array $beatTitles): string
    {
        $kind = $field === 'character_ids' ? 'characters' : 'wiki entries';
        $lines = ["Chapter \"{$title}\" (index {$index}):", "  - {$field} is empty, but beats reference these {$kind}:"];

        foreach ($matches as $match) {
            $beats = array_map(fn ($i) => '"'.($beatTitles[$i] ?? "beat #{$i}").'"', $match['beats']);
            $lines[] = "      • {$match['name']} (id={$match['id']}) — beat ".implode(', ', $beats);
        }

        return implode("\n", $lines);
    }
}
```

- [ ] **Step 2: Verify file is well-formed**

Run: `php -l app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

(Do not commit yet — Task 3 wires this trait into ProposeChapterPlan and they ship together.)

---

## Task 3: Wire validation into ProposeChapterPlan (TDD)

**Files:**
- Modify: `app/Ai/Tools/Plot/ProposeChapterPlan.php`
- Create: `tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php`:

```php
<?php

use App\Ai\Tools\Plot\ProposeChapterPlan;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('rejects when a beat references a book character but character_ids is empty', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter entity links missing')
        ->toContain('character_ids is empty')
        ->toContain('Maja')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL'); // sentinel must NOT be emitted
});

it('rejects when a beat references a wiki entry but wiki_entry_ids is empty', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Voyager appears',
        'description' => 'The Voyager probe enters the gravitational lens.',
    ]);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('wiki_entry_ids is empty')
        ->toContain('Voyager')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('accepts when no entities are referenced (empty lists are valid)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Empty room',
        'description' => 'A nondescript empty room. No one is there.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'Opening', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat->id]],
        ],
    ]));

    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->not->toContain('Chapter entity links missing');
});

it('accepts when entities are referenced and lists are populated (specific picks not enforced)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $maja = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $john = Character::factory()->for($book, 'book')->create(['name' => 'John']); // not in beat
    $voyager = WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice 1',
        'chapters' => [[
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'beat_ids' => [$beat->id],
            'character_ids' => [$john->id], // agent picked the "wrong" character — that's OK; existence check passes
            'wiki_entry_ids' => [$voyager->id],
        ]],
    ]));

    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->not->toContain('Chapter entity links missing');
});

it('reports rejections for multiple chapters in one proposal', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat1 = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $beat2 = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'John waits',
        'description' => 'John waits in the lab.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    Character::factory()->for($book, 'book')->create(['name' => 'John']);
    WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeChapterPlan;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Slice 1',
        'chapters' => [
            ['title' => 'A', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat1->id]],
            ['title' => 'B', 'storyline_id' => $storyline->id, 'beat_ids' => [$beat2->id]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter "A" (index 0)')
        ->toContain('Chapter "B" (index 1)')
        ->toContain('Maja')
        ->toContain('John')
        ->toContain('Voyager');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php`
Expected: 4 of 5 tests fail (the "accepts when no entities" test passes by accident because today's tool produces the sentinel and the rejection check never fires).

- [ ] **Step 3: Wire validation into ProposeChapterPlan**

Edit `app/Ai/Tools/Plot/ProposeChapterPlan.php`:

Add the trait to the use list (line 5–6 area):

```php
use App\Ai\Tools\Plot\Concerns\CoercesBookId;
use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Ai\Tools\Plot\Concerns\ValidatesChapterEntityLinks;
```

And in the class body (line 34):

```php
use CoercesBookId, DecodesJsonPayload, ValidatesChapterEntityLinks;
```

Then in `handle()`, after the loop that builds `$writes` (currently around line 94, right before the `$sections = []` line), insert:

```php
        // Validate entity links before producing the preview / persisting.
        if ($bookId !== null) {
            $chapterDataForValidation = array_map(fn ($w) => $w['data'], $writes);
            $rejection = $this->validateChapterEntityLinks($bookId, $chapterDataForValidation);

            if ($rejection !== null) {
                return $rejection;
            }
        }
```

The placement matters: validation runs after `normalizeChapter` (so we have clean int arrays) and before `persistProposal` (so a rejection doesn't leave a stale proposal row).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php`
Expected: all 5 tests pass.

- [ ] **Step 5: Run the full ProposeChapterPlan test file to confirm no regressions**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeChapterPlanTest.php`
Expected: all existing tests still pass. (None of the existing tests stage characters/wiki entries that would be referenced by their beat descriptions, so validation doesn't fire.)

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Support/BeatEntityScanner.php \
        app/Ai/Tools/Plot/Concerns/ValidatesChapterEntityLinks.php \
        app/Ai/Tools/Plot/ProposeChapterPlan.php \
        tests/Unit/Ai/Support/BeatEntityScannerTest.php \
        tests/Feature/Ai/Tools/ProposeChapterPlanValidationTest.php
git commit -m "feat(plot-coach): reject ProposeChapterPlan when beats reference unlisted entities"
```

(Note: this batches Tasks 1, 2, and 3 into one commit since the trait is non-functional without a caller. If Task 1 was already committed standalone, just add the rest.)

---

## Task 4: Wire validation into ProposeBatch (TDD)

**Files:**
- Modify: `app/Ai/Tools/Plot/ProposeBatch.php`
- Create: `tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php`

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php`:

```php
<?php

use App\Ai\Tools\Plot\ProposeBatch;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('rejects a chapter write whose beats reference characters not in character_ids', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Single chapter slice',
        'writes' => [
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
            ]],
        ],
    ]));

    expect($result)
        ->toContain('Chapter entity links missing')
        ->toContain('Maja')
        ->not->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('passes through a chapter write with populated entity ids', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Maja boards',
        'description' => 'Maja boards the Voyager probe.',
    ]);
    $maja = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $voyager = WikiEntry::factory()->for($book, 'book')->create(['name' => 'Voyager']);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Single chapter slice',
        'writes' => [
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
                'character_ids' => [$maja->id],
                'wiki_entry_ids' => [$voyager->id],
            ]],
        ],
    ]));

    expect($result)->toContain('PLOT_COACH_BATCH_PROPOSAL');
});

it('does not validate non-chapter writes (a character write alongside a chapter)', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $plotPoint = PlotPoint::factory()->for($book, 'book')->create();
    $beat = Beat::factory()->for($plotPoint, 'plotPoint')->create([
        'title' => 'Empty room',
        'description' => 'A nondescript empty room.',
    ]);

    $tool = new ProposeBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'Mixed batch',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja']],
            ['type' => 'chapter', 'data' => [
                'title' => 'Opening',
                'storyline_id' => $storyline->id,
                'beat_ids' => [$beat->id],
            ]],
        ],
    ]));

    // Empty room beat doesn't reference any book entity, so chapter passes;
    // character write goes through unaffected.
    expect($result)
        ->toContain('PLOT_COACH_BATCH_PROPOSAL')
        ->toContain('Maja');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php`
Expected: the first test fails (no rejection); other two may pass by accident.

- [ ] **Step 3: Wire validation into ProposeBatch**

Edit `app/Ai/Tools/Plot/ProposeBatch.php`:

Add the trait import (around line 6–7):

```php
use App\Ai\Tools\Plot\Concerns\CoercesBookId;
use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Ai\Tools\Plot\Concerns\ValidatesChapterEntityLinks;
```

And in the class body (line 33):

```php
use CoercesBookId, DecodesJsonPayload, ValidatesChapterEntityLinks;
```

Then in `handle()`, after `$writes = $this->enrichWrites($bookId, $writes);` (around line 72), insert:

```php
        if ($bookId !== null) {
            $chapterWrites = [];
            foreach ($writes as $write) {
                if (is_array($write) && ($write['type'] ?? null) === 'chapter' && is_array($write['data'] ?? null)) {
                    // Skip update writes — they target an existing chapter, the
                    // beats may already be linked, and the agent isn't adding
                    // new beats. Only validate creates (no `id` key).
                    if (! isset($write['data']['id'])) {
                        $chapterWrites[] = $write['data'];
                    }
                }
            }

            if ($chapterWrites !== []) {
                $rejection = $this->validateChapterEntityLinks($bookId, $chapterWrites);

                if ($rejection !== null) {
                    return $rejection;
                }
            }
        }
```

The placement: after `enrichWrites` (so update writes carry `_existing_*` hints, but we only validate creates anyway), before grouping for the preview.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php`
Expected: all 3 tests pass.

- [ ] **Step 5: Run the full ProposeBatch test file to confirm no regressions**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeBatchTest.php`
Expected: all existing tests still pass.

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/Plot/ProposeBatch.php tests/Feature/Ai/Tools/ProposeBatchChapterValidationTest.php
git commit -m "feat(plot-coach): reject ProposeBatch chapter writes missing referenced entities"
```

---

## Task 5: Update ProposeChapterPlan and ProposeBatch tool descriptions

**Files:**
- Modify: `app/Ai/Tools/Plot/ProposeChapterPlan.php` (description string at line 38)
- Modify: `app/Ai/Tools/Plot/ProposeBatch.php` (description string at line 39)

These are agent-facing prose changes — no automated test. Validation behavior is already covered by Tasks 3 and 4.

- [ ] **Step 1: Update ProposeChapterPlan description**

In `app/Ai/Tools/Plot/ProposeChapterPlan.php`, replace the `description()` return string with:

```php
    public function description(): Stringable|string
    {
        return 'Presents a preview of chapter stubs you intend to add — one per proposed chapter, fully wiring beats, the POV character, supporting characters, the act, and any locations / items / lore / organizations (wiki entries) the chapter touches. Use this once beats exist and the author has agreed to break structure into chapters. The author will approve in chat before anything is persisted. Additive only: chapters whose (storyline, title) already exist will be reused (beats / characters / wiki entries re-attached without detaching), never renamed or deleted. Pass `chapters` as a JSON-encoded string of an array of `{"title": string, "storyline_id": int, "act_id"?: int, "pov_character_id"?: int, "beat_ids"?: int[], "character_ids": int[], "wiki_entry_ids": int[]}` objects. `character_ids` and `wiki_entry_ids` are REQUIRED on every chapter — list every supporting character and every location/item/organization/lore concept whose name appears in the attached beats\' descriptions. POV is added to the supporting cast pivot automatically; do not repeat it in `character_ids`. Empty arrays (`[]`) are valid only when the beats reference no known entities; otherwise the tool will reject the proposal and you must retry with the missing entities included. Example chapter: `{"title": "Madeira: Apparat-Anflug", "storyline_id": 12, "act_id": 3, "pov_character_id": 44, "beat_ids": [88, 89], "character_ids": [42, 47], "wiki_entry_ids": [12, 18]}`.';
    }
```

(Key changes: `character_ids` and `wiki_entry_ids` no longer have `?` — marked required in the type sketch; explicit "REQUIRED on every chapter" sentence; explicit empty-array rule; one fully-populated example.)

- [ ] **Step 2: Update ProposeBatch description**

In `app/Ai/Tools/Plot/ProposeBatch.php`, the `description()` returns one large prose string. Find the substring `chapters (fully wired with storyline, beats, POV character, supporting characters via \`character_ids\`, locations/items/lore via \`wiki_entry_ids\`, and act)` and replace it with:

```
chapters (fully wired with storyline, beats, POV character, supporting characters via REQUIRED `character_ids`, locations/items/lore via REQUIRED `wiki_entry_ids`, and act — both lists must include every entity whose name appears in the attached beats; empty lists are valid only when no entities are referenced)
```

- [ ] **Step 3: Sanity-check both files load**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ProposeChapterPlanTest.php tests/Feature/Ai/Tools/ProposeBatchTest.php`
Expected: all existing tests still pass (description text isn't asserted on by tests).

- [ ] **Step 4: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/Plot/ProposeChapterPlan.php app/Ai/Tools/Plot/ProposeBatch.php
git commit -m "docs(plot-coach): mark character_ids/wiki_entry_ids required in tool schemas"
```

---

## Task 6: Rewrite PlotCoachAgent chapter-proposal guidance

**Files:**
- Modify: `app/Ai/Agents/PlotCoachAgent.php`

Agent-facing instruction prose. No automated test — verified end-to-end in Task 9.

- [ ] **Step 1: Locate the chapter-proposal guidance block**

Open `app/Ai/Agents/PlotCoachAgent.php` and find the `refinementGuidance()` method. The current block (around lines 634–640) reads:

```
        - Each proposed chapter must specify title + storyline_id. ALWAYS wire what you know:
          - `beat_ids` — every beat this chapter dramatizes (N:1 is fine).
          - `pov_character_id` — the POV for the chapter (single character).
          - `character_ids` — every other character that appears or is implicated in this chapter (supporting cast). Pull these from the beat descriptions and from `plot_point.character_ids` on the beats' parent plot points. POV does NOT need to be repeated here — it's a separate pivot.
          - `wiki_entry_ids` — every location, item, organization, or lore concept the chapter touches. Pull these from the beat descriptions and from the bible. A chapter set in Jakutsk that references the alien material and ETH Zurich attaches all three.
          - `act_id` — the act this chapter sits in. Inherit from the beats' parent plot_points.
        - The goal is a fully-wired stub: when the author opens the chapter, the storyline / act / POV / supporting cast / beats / wiki entries are ALL pre-attached. They only have to write the prose. Don't leave wiring for "later" if you can infer it from the saved entities now.
```

- [ ] **Step 2: Replace it with the per-chapter checklist + example**

Replace those lines with:

```
        - Each proposed chapter must specify title + storyline_id. Per-chapter checklist — work through this for EVERY chapter you propose:
          1. `beat_ids` — every beat this chapter dramatizes (N:1 is fine).
          2. `pov_character_id` — the POV for the chapter (single character). The server adds this to the supporting cast pivot automatically; do NOT repeat it in `character_ids`.
          3. `character_ids` — REQUIRED. Read each attached beat's description. List every supporting character whose name appears, plus any character on the beats' parent `plot_point.character_ids`. Empty array `[]` is valid ONLY if no beats reference any known character. Otherwise the tool rejects the proposal and you retry.
          4. `wiki_entry_ids` — REQUIRED. Read each attached beat's description. List every location, item, organization, or lore concept whose name appears, plus any relevant entries from the bible. A chapter set in Jakutsk that references the alien material and ETH Zurich attaches all three. Empty array `[]` is valid ONLY if no beats reference any known wiki entry.
          5. `act_id` — the act this chapter sits in. Inherit from the beats' parent plot_points.
        - The goal is a fully-wired stub: when the author opens the chapter, the storyline / act / POV / supporting cast / beats / wiki entries are ALL pre-attached. They only have to write the prose. Don't leave wiring for "later" if you can infer it from the saved entities now.
        - Example fully-wired chapter: `{"title": "Madeira: Apparat-Anflug", "storyline_id": 12, "act_id": 3, "pov_character_id": 44, "beat_ids": [88, 89], "character_ids": [42, 47], "wiki_entry_ids": [12, 18]}`.
```

(Key changes: numbered checklist; explicit "REQUIRED" + retry-loop language on `character_ids` / `wiki_entry_ids`; removed the misleading "POV does NOT need to be repeated here" line in favor of clarifying that the server adds POV automatically; concrete example.)

- [ ] **Step 3: Sanity-check the full PlotCoachAgent test file still passes**

Run: `php artisan test --compact tests/Feature/Ai/PlotCoachAgentTest.php`
Expected: all existing tests still pass (instruction text isn't asserted on by tests).

- [ ] **Step 4: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Agents/PlotCoachAgent.php
git commit -m "feat(plot-coach): rewrite chapter-proposal guidance with required-entity checklist"
```

---

## Task 7: POV auto-include on chapter create + update (TDD)

**Files:**
- Modify: `app/Services/PlotCoachBatchService.php`
- Create: `tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php`

The `character_chapter` pivot has `role` (default `'mentioned'`) and `notes` (nullable) columns. POV is `'protagonist'` (matches `WikiController.php:38`). Auto-include must pass `role => 'protagonist'` explicitly so POV doesn't end up labeled `'mentioned'`.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php`:

```php
<?php

use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Services\PlotCoachBatchService;
use Illuminate\Support\Facades\DB;

it('auto-attaches the POV character to character_chapter on chapter create', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            // No character_ids supplied — POV must still land in the pivot.
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $povRole = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->where('character_id', $pov->id)
        ->value('role');

    expect($povRole)->toBe('protagonist');
});

it('preserves supporting cast when POV is auto-included', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $supporting = Character::factory()->for($book, 'book')->create(['name' => 'John']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            'character_ids' => [$supporting->id],
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $linkedIds = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->pluck('character_id')
        ->all();

    expect($linkedIds)->toEqualCanonicalizing([$pov->id, $supporting->id]);
});

it('auto-attaches POV when an update sets pov_character_id on a chapter that did not have one', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $newPov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    // Existing chapter, no POV, no pivot rows.
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['pov_character_id' => null]);

    $service = app(PlotCoachBatchService::class);
    $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'id' => $chapter->id,
            'pov_character_id' => $newPov->id,
        ],
    ]], 'test');

    $povRole = DB::table('character_chapter')
        ->where('chapter_id', $chapter->id)
        ->where('character_id', $newPov->id)
        ->value('role');

    expect($povRole)->toBe('protagonist');
});

it('does not duplicate POV in the pivot when character_ids already includes the POV id, and forces role=protagonist', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            'character_ids' => [$pov->id], // agent attached POV — service shouldn't duplicate; should ensure role
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $povRows = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->where('character_id', $pov->id)
        ->get();

    expect($povRows)->toHaveCount(1);
    expect($povRows->first()->role)->toBe('protagonist');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php`
Expected: tests 1, 2, 3 fail (POV not in pivot); test 4 may pass already because the agent's explicit attach already fires.

- [ ] **Step 3: Add POV auto-include in writeChapter (create path)**

In `app/Services/PlotCoachBatchService.php`, after the `attach` loop in `writeChapter()` (around line 1052, right after `$chapter->{$relation}()->attach($ids);` block closes), add:

```php
        if ($povCharacterId !== null) {
            $chapter->characters()->syncWithoutDetaching([
                $povCharacterId => ['role' => 'protagonist'],
            ]);
            $chapter->characters()->updateExistingPivot($povCharacterId, ['role' => 'protagonist']);
        }
```

So the relevant section becomes:

```php
        foreach ($pivotIds as $relation => $ids) {
            if ($ids) {
                $chapter->{$relation}()->attach($ids);
            }
        }

        if ($povCharacterId !== null) {
            $chapter->characters()->syncWithoutDetaching([
                $povCharacterId => ['role' => 'protagonist'],
            ]);
            $chapter->characters()->updateExistingPivot($povCharacterId, ['role' => 'protagonist']);
        }

        return ['type' => 'chapter', 'id' => $chapter->id];
```

Two-step rationale: `syncWithoutDetaching` only creates a new pivot row when one doesn't exist; it does NOT update pivot attributes on existing rows. If the agent already attached POV via `character_ids`, that row was created with the column default `role='mentioned'`. The follow-up `updateExistingPivot` call forces `role='protagonist'` on the POV row regardless of whether step 1 created it or it pre-existed. The two calls are idempotent — running them twice produces the same result.

- [ ] **Step 4: Add POV auto-include in updateChapter**

In `updateChapter()`, after the `$chapter->{$relation}()->sync($ids);` loop (around line 1153), add:

```php
        if ($chapter->pov_character_id !== null) {
            $chapter->characters()->syncWithoutDetaching([
                $chapter->pov_character_id => ['role' => 'protagonist'],
            ]);
            $chapter->characters()->updateExistingPivot($chapter->pov_character_id, ['role' => 'protagonist']);
        }
```

By this point in `updateChapter`, `$chapter->update($patch)` has already run, so `$chapter->pov_character_id` reflects the post-update value. This handles all cases correctly:
- Update changed POV → new POV gets auto-attached with role='protagonist'.
- Update synced `character_ids` (which `sync` may have detached the previous POV pivot row) → POV re-attached.
- Update set `pov_character_id` to null → no auto-include (the user explicitly removed POV).
- Update didn't touch POV but synced character_ids → existing POV re-attached.

Same two-step (syncWithoutDetaching + updateExistingPivot) as Step 3 to ensure `role='protagonist'` regardless of whether the row pre-existed.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php`
Expected: all 4 tests pass.

- [ ] **Step 6: Run the full ApplyPlotCoachBatch test file to confirm no regressions**

Run: `php artisan test --compact tests/Feature/Ai/Tools/ApplyPlotCoachBatchTest.php`
Expected: all existing tests still pass.

- [ ] **Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PlotCoachBatchService.php tests/Feature/Ai/Tools/ApplyPlotCoachBatchPovIncludeTest.php
git commit -m "feat(plot-coach): auto-attach POV character to character_chapter pivot on persist"
```

---

## Task 8: Full-suite verification

- [ ] **Step 1: Run the full Pest suite**

Run: `php artisan test --compact`
Expected: all tests pass. If anything new breaks, investigate before moving on (especially `tests/Unit/GuardrailsTest.php` and the Ai/Tools and Ai/Plot test files).

- [ ] **Step 2: Run pint over the whole change set**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no diff or only formatting normalizations.

- [ ] **Step 3: Native migrate (no migration in this plan, but verify cleanly)**

This plan adds no migrations, so `native:migrate` is not required. If `git diff main -- database/migrations/` shows nothing new, skip; otherwise run `php artisan native:migrate`.

```bash
git diff main -- database/migrations/ | head -5
```

Expected: empty output. If any migrations changed, run `php artisan native:migrate` per CLAUDE.md.

---

## Task 9: End-to-end verification on the live Voyage book

This is a manual smoke test using the running NativePHP app, not an automated test. It validates the agent's behavior end-to-end, which automated tests cannot fully cover (the LLM is in the loop).

- [ ] **Step 1: Confirm the runtime DB state for Voyage matches the spec's premise**

Run:

```bash
DB_DATABASE=database/nativephp.sqlite php artisan tinker --execute '
$book = App\Models\Book::where("title", "Voyage")->first();
$pivotChars = DB::table("character_chapter")
    ->join("chapters", "chapters.id", "=", "character_chapter.chapter_id")
    ->where("chapters.book_id", $book->id)->count();
$pivotWiki = DB::table("wiki_entry_chapter")
    ->join("chapters", "chapters.id", "=", "wiki_entry_chapter.chapter_id")
    ->where("chapters.book_id", $book->id)->count();
echo "char_chapter: {$pivotChars}\nwiki_entry_chapter: {$pivotWiki}\n";
'
```

Expected: `char_chapter: 2` and `wiki_entry_chapter: 1` (the Epilog's existing rows).

- [ ] **Step 2: Open the running NativePHP app and start a plot-coach session in Voyage**

Open the app (the user runs it locally), open the Voyage book, open the plot coach.

- [ ] **Step 3: Ask the agent to redo a chapter plan**

Type into the plot-coach chat (example phrasing — adapt to what feels natural):

> "Re-plan chapters 270 through 273 with full entity wiring."

- [ ] **Step 4: Verify the rejection-and-retry loop runs**

Watch the chat. The agent's first `ProposeChapterPlan` call may produce empty `character_ids` / `wiki_entry_ids`. The tool will return the rejection. The agent should silently retry with populated entities. The user-visible output should be the final approved preview, not the rejection text.

- [ ] **Step 5: Approve the proposal in chat and verify pivot population**

After approval, run:

```bash
DB_DATABASE=database/nativephp.sqlite php artisan tinker --execute '
$book = App\Models\Book::where("title", "Voyage")->first();
foreach ($book->chapters()->whereIn("id", [270, 271, 272, 273])->get() as $c) {
    $w = $c->wikiEntries()->count();
    $ch = $c->characters()->count();
    echo "[{$c->id}] {$c->title} → wiki:{$w} chars:{$ch}\n";
}
'
```

Expected: `wiki:>0` and `chars:>=1` for each chapter (POV included via auto-attach; supporting cast via the agent's submission).

- [ ] **Step 6: Open the editor and confirm the wiki panel shows linked entries**

In the running app, open chapter 270 in the editor. Open the wiki panel. It should now show the linked wiki entries and characters under the "Connected to Chapter" section.

- [ ] **Step 7: Read the plot-coach session log for any rejection messages**

Use the laravel-boost MCP `read-log-entries` tool, or:

```bash
tail -200 storage/logs/laravel.log | grep -i "Chapter entity links missing\|ValidatesChapterEntityLinks"
```

Expected: rejection messages may appear if the agent retried; if so, confirm the agent's *next* `ProposeChapterPlan` call landed with the entities populated.

- [ ] **Step 8: If any chapter still has empty pivots, investigate before declaring success**

The most likely causes:
- The matched entity is shorter than 3 chars (relax `MIN_NAME_LENGTH` only with caution).
- A character/wiki entry exists in the bible but has a slightly different spelling than what's in the beat description (this is the alias problem the spec deferred).
- The agent is exceeding the implicit retry budget. Check the AI provider logs for repeated tool calls.

Document any remaining issues in a follow-up plan rather than expanding this one.

---

## Self-Review Checklist

After implementing, run through:

- [ ] Spec section "Components" — every numbered item maps to a task above (BeatEntityScanner → Task 1; trait → Task 2; ProposeChapterPlan validation → Task 3; ProposeBatch validation → Task 4; tool descriptions → Task 5; agent instructions → Task 6; POV auto-include → Task 7).
- [ ] Spec section "Testing" — every test bullet has a matching test in Tasks 1, 3, 4, or 7.
- [ ] Spec "Out of Scope" — verify nothing from that list snuck in (no manual editor UI, no fallback inference, no backfill, no aliases, no manual-chapter-controller validation).
- [ ] No placeholders in this plan: search this file for `TBD`, `TODO`, `implement later`, `appropriate error handling` — none should remain.
- [ ] Type / method consistency: `BeatEntityScanner::findReferenced`, `ValidatesChapterEntityLinks::validateChapterEntityLinks`, the pivot column `role => 'protagonist'`, and the `$povCharacterId` variable in `writeChapter` are referenced consistently across every task that touches them.

# Plot Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Save the Cat + Story Circle templates, richer swim lane cards (description, characters, word count), and a character-plot point many-to-many relationship.

**Architecture:** Three independent features sharing one new pivot table. Templates are data-only. Richer cards consume the new character relationship. The character pivot provides backend infrastructure for both card display and detail panel management.

**Tech Stack:** Laravel 12, Pest 4, Inertia.js v2, React 19, Tailwind CSS v4, i18next

**Spec:** `docs/superpowers/specs/2026-03-19-plot-improvements-design.md`

---

## Task 1: Create character_plot_point Migration & Enum

**Files:**
- Create: `database/migrations/2026_03_19_000001_create_character_plot_point_table.php`
- Create: `app/Enums/CharacterPlotPointRole.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration create_character_plot_point_table --no-interaction
```

Replace the generated file contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_plot_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plot_point_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('key');
            $table->timestamps();

            $table->unique(['character_id', 'plot_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_plot_point');
    }
};
```

- [ ] **Step 2: Create the PHP enum**

Create `app/Enums/CharacterPlotPointRole.php`:

```php
<?php

namespace App\Enums;

enum CharacterPlotPointRole: string
{
    case Key = 'key';
    case Supporting = 'supporting';
    case Mentioned = 'mentioned';
}
```

- [ ] **Step 3: Run migration against both databases**

```bash
php artisan migrate --no-interaction
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

Expected: Both succeed, `character_plot_point` table created.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*create_character_plot_point* app/Enums/CharacterPlotPointRole.php
git commit -m "feat(plot): add character_plot_point migration and CharacterPlotPointRole enum"
```

---

## Task 2: Add Model Relationships

**Files:**
- Modify: `app/Models/PlotPoint.php` — add `characters()` method
- Modify: `app/Models/Character.php` — add `plotPoints()` method

- [ ] **Step 1: Write test for PlotPoint->characters relationship**

Add to `tests/Feature/PlotPointControllerTest.php`:

```php
it('can attach characters to a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $character = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($character->id, ['role' => 'key']);

    expect($plotPoint->characters)->toHaveCount(1)
        ->and($plotPoint->characters->first()->id)->toBe($character->id)
        ->and($plotPoint->characters->first()->pivot->role)->toBe('key');
});
```

Add `use App\Models\Character;` at the top of the file.

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="can attach characters to a plot point"
```

Expected: FAIL — `characters()` method does not exist on PlotPoint.

- [ ] **Step 3: Add characters() to PlotPoint model**

In `app/Models/PlotPoint.php`, add the import and relationship:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Add method after `incomingConnections()`:

```php
/**
 * @return BelongsToMany<Character, $this>
 */
public function characters(): BelongsToMany
{
    return $this->belongsToMany(Character::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

- [ ] **Step 4: Add plotPoints() to Character model**

In `app/Models/Character.php`, add import and method after `chapters()`:

```php
use App\Models\PlotPoint;
```

```php
/**
 * @return BelongsToMany<PlotPoint, $this>
 */
public function plotPoints(): BelongsToMany
{
    return $this->belongsToMany(PlotPoint::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --compact --filter="can attach characters to a plot point"
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/PlotPoint.php app/Models/Character.php tests/Feature/PlotPointControllerTest.php
git commit -m "feat(plot): add characters<->plotPoints belongsToMany relationships"
```

---

## Task 3: Update PlotPointController to Sync Characters

**Files:**
- Modify: `app/Http/Requests/UpdatePlotPointRequest.php` — add characters validation
- Modify: `app/Http/Controllers/PlotPointController.php` — sync characters on update
- Modify: `app/Http/Controllers/PlotController.php` — eager-load characters, pass book characters

- [ ] **Step 1: Write tests for character sync via update endpoint**

Add to `tests/Feature/PlotPointControllerTest.php`:

```php
it('syncs characters when updating a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char1 = Character::factory()->create(['book_id' => $book->id]);
    $char2 = Character::factory()->create(['book_id' => $book->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char1->id, 'role' => 'key'],
            ['id' => $char2->id, 'role' => 'supporting'],
        ],
    ])->assertOk();

    $plotPoint->refresh();
    expect($plotPoint->characters)->toHaveCount(2);
    expect($plotPoint->characters->firstWhere('id', $char1->id)->pivot->role)->toBe('key');
    expect($plotPoint->characters->firstWhere('id', $char2->id)->pivot->role)->toBe('supporting');
});

it('replaces previous characters on sync', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char1 = Character::factory()->create(['book_id' => $book->id]);
    $char2 = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($char1->id, ['role' => 'key']);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char2->id, 'role' => 'mentioned'],
        ],
    ])->assertOk();

    $plotPoint->refresh();
    expect($plotPoint->characters)->toHaveCount(1)
        ->and($plotPoint->characters->first()->id)->toBe($char2->id);
});

it('detaches all characters with empty array', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char = Character::factory()->create(['book_id' => $book->id]);

    $plotPoint->characters()->attach($char->id, ['role' => 'key']);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [],
    ])->assertOk();

    expect($plotPoint->fresh()->characters)->toHaveCount(0);
});

it('rejects characters from another book', function () {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $otherChar = Character::factory()->create(['book_id' => $otherBook->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $otherChar->id, 'role' => 'key'],
        ],
    ])->assertUnprocessable();
});

it('rejects invalid character role', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);
    $char = Character::factory()->create(['book_id' => $book->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => $char->id, 'role' => 'villain'],
        ],
    ])->assertUnprocessable();
});

it('rejects non-existent character ids', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'characters' => [
            ['id' => 99999, 'role' => 'key'],
        ],
    ])->assertUnprocessable();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="syncs characters|replaces previous|detaches all|rejects characters from|rejects invalid character|rejects non-existent"
```

Expected: FAIL — validation rules don't exist yet.

- [ ] **Step 3: Update UpdatePlotPointRequest**

In `app/Http/Requests/UpdatePlotPointRequest.php`, add to `rules()`:

```php
'characters' => ['sometimes', 'array'],
'characters.*.id' => ['required', 'integer', Rule::exists('characters', 'id')->where('book_id', $this->route('book')->id)],
'characters.*.role' => ['required', 'string', Rule::in(['key', 'supporting', 'mentioned'])],
```

- [ ] **Step 4: Update PlotPointController@update to sync characters**

In `app/Http/Controllers/PlotPointController.php`, modify the `update` method:

```php
public function update(UpdatePlotPointRequest $request, Book $book, PlotPoint $plotPoint): JsonResponse
{
    $validated = $request->validated();
    $characters = null;

    if (array_key_exists('characters', $validated)) {
        $characters = collect($validated['characters'])->mapWithKeys(
            fn (array $item) => [$item['id'] => ['role' => $item['role']]]
        )->all();
        unset($validated['characters']);
    }

    $plotPoint->update($validated);

    if ($characters !== null) {
        $plotPoint->characters()->sync($characters);
    }

    $plotPoint->load(['storyline', 'act', 'intendedChapter', 'characters']);

    return response()->json($plotPoint);
}
```

- [ ] **Step 5: Update PlotController@index**

In `app/Http/Controllers/PlotController.php`:

Add `plotPoints.characters` to the eager-load array:

```php
'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
'plotPoints.characters',
```

Load and pass book characters:

```php
$characters = $book->characters()->orderBy('name')->get();
```

Add to the Inertia render props:

```php
'characters' => $characters,
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter="syncs characters|replaces previous|detaches all|rejects characters from|rejects invalid character"
```

Expected: All PASS (including "rejects non-existent character ids")

- [ ] **Step 7: Run all existing plot tests for regression**

```bash
php artisan test --compact tests/Feature/PlotPointControllerTest.php tests/Feature/PlotSetupControllerTest.php tests/Feature/PlotPageTest.php
```

Expected: All PASS

- [ ] **Step 8: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/UpdatePlotPointRequest.php app/Http/Controllers/PlotPointController.php app/Http/Controllers/PlotController.php tests/Feature/PlotPointControllerTest.php
git commit -m "feat(plot): sync characters on plot point update, eager-load in controller"
```

---

## Task 4: Add Save the Cat & Story Circle Templates

**Files:**
- Modify: `resources/js/lib/plot-templates.ts` — add 2 new raw templates
- Modify: `resources/js/i18n/en/plot.json` — add template i18n keys
- Modify: `resources/js/i18n/de/plot.json` — add template i18n keys
- Modify: `resources/js/i18n/es/plot.json` — add template i18n keys
- Modify: `app/Http/Requests/SetupPlotStructureRequest.php` — add new keys to Rule::in

- [ ] **Step 1: Write tests for new templates**

Add to `tests/Feature/PlotSetupControllerTest.php`:

```php
test('it creates save-the-cat structure with 3 acts and 15 beats', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'save_the_cat',
        'acts' => [
            [
                'title' => 'Setup',
                'color' => '#B87333',
                'beats' => [
                    ['title' => 'Opening Image', 'type' => 'setup'],
                    ['title' => 'Theme Stated', 'type' => 'setup'],
                    ['title' => 'Set-Up', 'type' => 'setup'],
                    ['title' => 'Catalyst', 'type' => 'conflict'],
                    ['title' => 'Debate', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'Confrontation',
                'color' => '#8B6914',
                'beats' => [
                    ['title' => 'Break Into Two', 'type' => 'turning_point'],
                    ['title' => 'B Story', 'type' => 'setup'],
                    ['title' => 'Fun and Games', 'type' => 'conflict'],
                    ['title' => 'Midpoint', 'type' => 'turning_point'],
                    ['title' => 'Bad Guys Close In', 'type' => 'conflict'],
                    ['title' => 'All Is Lost', 'type' => 'conflict'],
                    ['title' => 'Dark Night of the Soul', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'Resolution',
                'color' => '#6B4423',
                'beats' => [
                    ['title' => 'Break Into Three', 'type' => 'turning_point'],
                    ['title' => 'Finale', 'type' => 'resolution'],
                    ['title' => 'Final Image', 'type' => 'resolution'],
                ],
            ],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(3)
        ->and($book->plotPoints()->count())->toBe(15);
});

test('it creates story-circle structure with 2 acts and 8 beats', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'story_circle',
        'acts' => [
            [
                'title' => 'The Descent',
                'color' => '#B87333',
                'beats' => [
                    ['title' => 'You', 'type' => 'setup'],
                    ['title' => 'Need', 'type' => 'conflict'],
                    ['title' => 'Go', 'type' => 'turning_point'],
                    ['title' => 'Search', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'The Return',
                'color' => '#8B6914',
                'beats' => [
                    ['title' => 'Find', 'type' => 'turning_point'],
                    ['title' => 'Take', 'type' => 'conflict'],
                    ['title' => 'Return', 'type' => 'resolution'],
                    ['title' => 'Change', 'type' => 'resolution'],
                ],
            ],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(2)
        ->and($book->plotPoints()->count())->toBe(8);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="save-the-cat|story-circle"
```

Expected: FAIL — validation rejects `save_the_cat` and `story_circle` template keys.

- [ ] **Step 3: Update SetupPlotStructureRequest**

In `app/Http/Requests/SetupPlotStructureRequest.php` line 22, change:

```php
'template' => ['required', 'string', Rule::in(['three_act', 'five_act', 'heros_journey'])],
```

to:

```php
'template' => ['required', 'string', Rule::in(['three_act', 'five_act', 'heros_journey', 'save_the_cat', 'story_circle'])],
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter="save-the-cat|story-circle"
```

Expected: PASS

- [ ] **Step 5: Add raw template data to plot-templates.ts**

In `resources/js/lib/plot-templates.ts`, add these entries to the `RAW_TEMPLATES` array after the `heros_journey` entry:

```typescript
{
    key: 'save_the_cat',
    acts: [
        {
            color: '#B87333',
            beats: [
                { type: 'setup' },
                { type: 'setup' },
                { type: 'setup' },
                { type: 'conflict' },
                { type: 'conflict' },
            ],
        },
        {
            color: '#8B6914',
            beats: [
                { type: 'turning_point' },
                { type: 'setup' },
                { type: 'conflict' },
                { type: 'turning_point' },
                { type: 'conflict' },
                { type: 'conflict' },
                { type: 'conflict' },
            ],
        },
        {
            color: '#6B4423',
            beats: [
                { type: 'turning_point' },
                { type: 'resolution' },
                { type: 'resolution' },
            ],
        },
    ],
},
{
    key: 'story_circle',
    acts: [
        {
            color: '#B87333',
            beats: [
                { type: 'setup' },
                { type: 'conflict' },
                { type: 'turning_point' },
                { type: 'conflict' },
            ],
        },
        {
            color: '#8B6914',
            beats: [
                { type: 'turning_point' },
                { type: 'conflict' },
                { type: 'resolution' },
                { type: 'resolution' },
            ],
        },
    ],
},
```

- [ ] **Step 6: Add i18n keys to en/plot.json**

Add these keys to `resources/js/i18n/en/plot.json`:

```json
"emptyState.template.save_the_cat.name": "Save the Cat",
"emptyState.template.save_the_cat.description": "Blake Snyder's 15-beat structure. The go-to framework for screenwriters and novelists.",
"emptyState.template.story_circle.name": "Story Circle",
"emptyState.template.story_circle.description": "Dan Harmon's 8-step cycle. Simple, universal, great for character-driven stories.",

"template.save_the_cat.acts.0": "Setup",
"template.save_the_cat.acts.1": "Confrontation",
"template.save_the_cat.acts.2": "Resolution",
"template.save_the_cat.beats.0.0": "Opening Image",
"template.save_the_cat.beats.0.1": "Theme Stated",
"template.save_the_cat.beats.0.2": "Set-Up",
"template.save_the_cat.beats.0.3": "Catalyst",
"template.save_the_cat.beats.0.4": "Debate",
"template.save_the_cat.beats.1.0": "Break Into Two",
"template.save_the_cat.beats.1.1": "B Story",
"template.save_the_cat.beats.1.2": "Fun and Games",
"template.save_the_cat.beats.1.3": "Midpoint",
"template.save_the_cat.beats.1.4": "Bad Guys Close In",
"template.save_the_cat.beats.1.5": "All Is Lost",
"template.save_the_cat.beats.1.6": "Dark Night of the Soul",
"template.save_the_cat.beats.2.0": "Break Into Three",
"template.save_the_cat.beats.2.1": "Finale",
"template.save_the_cat.beats.2.2": "Final Image",

"template.story_circle.acts.0": "The Descent",
"template.story_circle.acts.1": "The Return",
"template.story_circle.beats.0.0": "You",
"template.story_circle.beats.0.1": "Need",
"template.story_circle.beats.0.2": "Go",
"template.story_circle.beats.0.3": "Search",
"template.story_circle.beats.1.0": "Find",
"template.story_circle.beats.1.1": "Take",
"template.story_circle.beats.1.2": "Return",
"template.story_circle.beats.1.3": "Change",
```

- [ ] **Step 7: Add i18n keys to de/plot.json**

Add these keys to `resources/js/i18n/de/plot.json`:

```json
"emptyState.template.save_the_cat.name": "Save the Cat",
"emptyState.template.save_the_cat.description": "Blake Snyders 15-Beat-Struktur. Das Standardgerüst für Drehbuchautoren und Romanschreiber.",
"emptyState.template.story_circle.name": "Story Circle",
"emptyState.template.story_circle.description": "Dan Harmons 8-Schritte-Zyklus. Einfach, universell, ideal für charaktergetriebene Geschichten.",

"template.save_the_cat.acts.0": "Einführung",
"template.save_the_cat.acts.1": "Konfrontation",
"template.save_the_cat.acts.2": "Auflösung",
"template.save_the_cat.beats.0.0": "Eingangsbild",
"template.save_the_cat.beats.0.1": "Thema angedeutet",
"template.save_the_cat.beats.0.2": "Einführung",
"template.save_the_cat.beats.0.3": "Katalysator",
"template.save_the_cat.beats.0.4": "Debatte",
"template.save_the_cat.beats.1.0": "Übergang in Akt Zwei",
"template.save_the_cat.beats.1.1": "B-Geschichte",
"template.save_the_cat.beats.1.2": "Spaß und Spiele",
"template.save_the_cat.beats.1.3": "Mittelpunkt",
"template.save_the_cat.beats.1.4": "Die Schlinge zieht sich zu",
"template.save_the_cat.beats.1.5": "Alles ist verloren",
"template.save_the_cat.beats.1.6": "Die dunkle Nacht der Seele",
"template.save_the_cat.beats.2.0": "Übergang in Akt Drei",
"template.save_the_cat.beats.2.1": "Finale",
"template.save_the_cat.beats.2.2": "Schlussbild",

"template.story_circle.acts.0": "Der Abstieg",
"template.story_circle.acts.1": "Die Rückkehr",
"template.story_circle.beats.0.0": "Du",
"template.story_circle.beats.0.1": "Bedürfnis",
"template.story_circle.beats.0.2": "Aufbruch",
"template.story_circle.beats.0.3": "Suche",
"template.story_circle.beats.1.0": "Fund",
"template.story_circle.beats.1.1": "Nehmen",
"template.story_circle.beats.1.2": "Rückkehr",
"template.story_circle.beats.1.3": "Wandlung",
```

- [ ] **Step 8: Add i18n keys to es/plot.json**

Add these keys to `resources/js/i18n/es/plot.json`:

```json
"emptyState.template.save_the_cat.name": "Save the Cat",
"emptyState.template.save_the_cat.description": "La estructura de 15 beats de Blake Snyder. El marco preferido por guionistas y novelistas.",
"emptyState.template.story_circle.name": "Story Circle",
"emptyState.template.story_circle.description": "El ciclo de 8 pasos de Dan Harmon. Simple, universal, ideal para historias centradas en personajes.",

"template.story_circle.acts.0": "El descenso",
"template.story_circle.acts.1": "El regreso",
"template.story_circle.beats.0.0": "Tú",
"template.story_circle.beats.0.1": "Necesidad",
"template.story_circle.beats.0.2": "Partir",
"template.story_circle.beats.0.3": "Buscar",
"template.story_circle.beats.1.0": "Encontrar",
"template.story_circle.beats.1.1": "Tomar",
"template.story_circle.beats.1.2": "Regresar",
"template.story_circle.beats.1.3": "Cambiar",

"template.save_the_cat.acts.0": "Introducción",
"template.save_the_cat.acts.1": "Confrontación",
"template.save_the_cat.acts.2": "Resolución",
"template.save_the_cat.beats.0.0": "Imagen inicial",
"template.save_the_cat.beats.0.1": "Tema planteado",
"template.save_the_cat.beats.0.2": "Presentación",
"template.save_the_cat.beats.0.3": "Catalizador",
"template.save_the_cat.beats.0.4": "Debate",
"template.save_the_cat.beats.1.0": "Entrada al segundo acto",
"template.save_the_cat.beats.1.1": "Historia B",
"template.save_the_cat.beats.1.2": "Diversión y juegos",
"template.save_the_cat.beats.1.3": "Punto medio",
"template.save_the_cat.beats.1.4": "Los malos se acercan",
"template.save_the_cat.beats.1.5": "Todo está perdido",
"template.save_the_cat.beats.1.6": "La noche oscura del alma",
"template.save_the_cat.beats.2.0": "Entrada al tercer acto",
"template.save_the_cat.beats.2.1": "Final",
"template.save_the_cat.beats.2.2": "Imagen final",
```

- [ ] **Step 9: Run Pint and all plot tests**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/PlotSetupControllerTest.php
```

Expected: All PASS (including new save-the-cat and story-circle tests)

- [ ] **Step 10: Commit**

```bash
git add resources/js/lib/plot-templates.ts resources/js/i18n/*/plot.json app/Http/Requests/SetupPlotStructureRequest.php tests/Feature/PlotSetupControllerTest.php
git commit -m "feat(plot): add Save the Cat and Story Circle templates"
```

---

## Task 5: Update TypeScript Types

**Files:**
- Modify: `resources/js/types/models.ts` — add CharacterPlotPointRole, CharacterPlotPointPivot, update PlotPoint

- [ ] **Step 1: Add new types to models.ts**

In `resources/js/types/models.ts`, add after the `CharacterChapterPivot` type:

```typescript
export type CharacterPlotPointRole = 'key' | 'supporting' | 'mentioned';

export type CharacterPlotPointPivot = {
    character_id: number;
    plot_point_id: number;
    role: CharacterPlotPointRole;
};
```

- [ ] **Step 2: Update PlotPoint type**

In the `PlotPoint` type definition, add after `incoming_connections?`:

```typescript
characters?: (Character & { pivot: CharacterPlotPointPivot })[];
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/types/models.ts
git commit -m "feat(plot): add CharacterPlotPointPivot and update PlotPoint type"
```

---

## Task 6: Enrich PlotPointCard Component

**Files:**
- Modify: `resources/js/components/plot/PlotPointCard.tsx` — add description, word count, character initials
- Modify: `resources/js/i18n/en/plot.json` — add card i18n keys

- [ ] **Step 1: Add i18n keys for card**

Add to `resources/js/i18n/en/plot.json`:

```json
"card.wordCount": "{{count}} words",
"card.moreCharacters": "+{{count}}",
```

Add equivalent to de and es files:
- de: `"card.wordCount": "{{count}} Wörter"`, `"card.moreCharacters": "+{{count}}"` (add to `de/plot.json`)
- es: `"card.wordCount": "{{count}} palabras"`, `"card.moreCharacters": "+{{count}}"` (add to `es/plot.json`)

- [ ] **Step 2: Update PlotPointCard component**

Replace the contents of `resources/js/components/plot/PlotPointCard.tsx`:

```tsx
import { useTranslation } from 'react-i18next';
import { STATUS_COLORS, TYPE_STYLES } from '@/lib/plot-constants';
import type { Character, PlotPoint } from '@/types/models';

type Props = {
    plotPoint: PlotPoint;
    chapterWordCount?: number;
    onClick: () => void;
};

function formatWordCount(count: number): string {
    if (count >= 1000) {
        return `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
    }
    return String(count);
}

const MAX_VISIBLE_CHARACTERS = 3;

export default function PlotPointCard({
    plotPoint,
    chapterWordCount,
    onClick,
}: Props) {
    const { t } = useTranslation('plot');
    const characters = plotPoint.characters ?? [];
    const visibleChars = characters.slice(0, MAX_VISIBLE_CHARACTERS);
    const overflowCount = characters.length - MAX_VISIBLE_CHARACTERS;

    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className="w-full rounded border border-border bg-surface-card px-2.5 py-2 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-start gap-1.5">
                <div
                    className="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    style={{
                        backgroundColor:
                            STATUS_COLORS[plotPoint.status] ?? '#B0A99F',
                    }}
                />
                <span className="text-xs leading-tight font-medium text-ink">
                    {plotPoint.title}
                </span>
            </div>

            {plotPoint.description && (
                <p className="mt-1 line-clamp-2 text-[11px] leading-[15px] text-ink-muted">
                    {plotPoint.description}
                </p>
            )}

            <div className="mt-1.5 flex items-center justify-between gap-1">
                <div className="flex items-center gap-1.5">
                    <span
                        className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                    >
                        {t(`type.${plotPoint.type}`)}
                    </span>
                    {chapterWordCount != null && chapterWordCount > 0 && (
                        <span className="text-[10px] text-ink-faint">
                            {t('card.wordCount', {
                                count: formatWordCount(chapterWordCount),
                            })}
                        </span>
                    )}
                </div>

                {characters.length > 0 && (
                    <div className="flex items-center gap-0.5">
                        {visibleChars.map((char) => (
                            <span
                                key={char.id}
                                title={char.name}
                                className="flex h-4 w-4 items-center justify-center rounded-full bg-neutral-bg text-[9px] font-semibold text-ink-soft uppercase"
                            >
                                {char.name.charAt(0)}
                            </span>
                        ))}
                        {overflowCount > 0 && (
                            <span className="text-[9px] text-ink-faint">
                                {t('card.moreCharacters', {
                                    count: overflowCount,
                                })}
                            </span>
                        )}
                    </div>
                )}
            </div>
        </button>
    );
}
```

- [ ] **Step 3: Update SwimLaneTimeline to pass chapterWordCount**

In `resources/js/components/plot/SwimLaneTimeline.tsx`, update the `PlotPointCard` rendering inside the storyline grid cells (around line 296).

Add a helper to compute word count from chapters, and pass it as a prop:

In the component function body (before `return`), add:

```tsx
const chapterWordCountMap = useMemo(() => {
    const map = new Map<number, number>();
    for (const ch of chapters) {
        map.set(ch.id, ch.word_count ?? 0);
    }
    return map;
}, [chapters]);
```

Note: The standalone `chapters` collection from `PlotController@index` already includes all book chapters with `word_count`, so iterating `acts.chapters` is unnecessary.

Add the `word_count` field to the `ChapterColumn` type:

```typescript
type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number | null;
    storyline_id: number;
    tension_score: number | null;
    word_count?: number;
};
```

Update the `PlotPointCard` usage to include `chapterWordCount`:

```tsx
<PlotPointCard
    key={pp.id}
    plotPoint={pp}
    chapterWordCount={
        pp.intended_chapter_id
            ? chapterWordCountMap.get(pp.intended_chapter_id)
            : pp.actual_chapter_id
              ? chapterWordCountMap.get(pp.actual_chapter_id)
              : undefined
    }
    onClick={() => onSelectPlotPoint(pp)}
/>
```

- [ ] **Step 4: Skip PlotPointList enrichment**

`PlotPointList` uses its own inline `PlotPointRow` component (not `PlotPointCard`). The richer card design applies only to the swim lane timeline view. The list view remains as-is — it already shows title, type, and storyline which is appropriate for its compact format.

- [ ] **Step 5: Update plot/index.tsx types and props**

In `resources/js/pages/plot/index.tsx`:

Update the `ChapterCol` type (around line 36) to include `word_count`:

```typescript
type ChapterCol = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
    word_count?: number;
};
```

Add `characters` to `PlotPageProps`:

```typescript
characters: Character[];
```

Add `Character` to the imports from `@/types/models`.

Destructure `characters` from the page props.

- [ ] **Step 6: Verify the build compiles**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build succeeds with no TypeScript errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/plot/PlotPointCard.tsx resources/js/components/plot/SwimLaneTimeline.tsx resources/js/components/plot/PlotPointList.tsx resources/js/pages/plot/index.tsx resources/js/i18n/*/plot.json
git commit -m "feat(plot): enrich PlotPointCard with description, word count, character initials"
```

---

## Task 7: Add Characters Section to DetailPanel

**Files:**
- Modify: `resources/js/components/plot/DetailPanel.tsx` — add characters section with add/remove/role UI
- Modify: `resources/js/i18n/en/plot.json` — add detail panel character i18n keys

- [ ] **Step 1: Add i18n keys**

Add to `resources/js/i18n/en/plot.json`:

```json
"detailPanel.characters": "Characters",
"detailPanel.addCharacter": "Add character",
"detailPanel.noCharacters": "No characters tagged",
"detailPanel.characterRole.key": "Key",
"detailPanel.characterRole.supporting": "Supporting",
"detailPanel.characterRole.mentioned": "Mentioned",
```

Add equivalents to de and es files.

- [ ] **Step 2: Update DetailPanel props and imports**

In `resources/js/components/plot/DetailPanel.tsx`:

The component already imports `X` from `lucide-react` (line 1). No new icon imports needed.

Update the `Props` type:

```typescript
type Props = {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    acts: Act[];
    connections: PlotPointConnection[];
    bookCharacters: Character[];
    onClose: () => void;
    onUpdate: (data: Record<string, unknown>) => void;
};
```

Add `Character` to the imports from `@/types/models`.

Destructure `bookCharacters` from props.

- [ ] **Step 3: Add characters section UI**

After the Status section (after the `</div>` that closes the status `Select`), add:

```tsx
{/* Characters */}
<div className="flex flex-col gap-2">
    <span className="text-[11px] font-medium tracking-[0.08em] text-ink-muted uppercase">
        {t('detailPanel.characters')}
    </span>

    {(plotPoint.characters ?? []).map((char) => (
        <div
            key={char.id}
            className="flex items-center justify-between gap-2"
        >
            <div className="flex items-center gap-2">
                <span className="flex h-5 w-5 items-center justify-center rounded-full bg-neutral-bg text-[10px] font-semibold text-ink-soft uppercase">
                    {char.name.charAt(0)}
                </span>
                <span className="text-[13px] text-ink-soft">
                    {char.name}
                </span>
            </div>
            <div className="flex items-center gap-1">
                <select
                    value={char.pivot?.role ?? 'key'}
                    onChange={(e) => {
                        const updated = (plotPoint.characters ?? []).map(
                            (c) =>
                                c.id === char.id
                                    ? {
                                          id: c.id,
                                          role: e.target.value,
                                      }
                                    : {
                                          id: c.id,
                                          role: c.pivot?.role ?? 'key',
                                      },
                        );
                        onUpdate({ characters: updated });
                    }}
                    className="rounded border border-border bg-surface-card px-1 py-0.5 text-[11px] text-ink-soft"
                >
                    <option value="key">
                        {t('detailPanel.characterRole.key')}
                    </option>
                    <option value="supporting">
                        {t('detailPanel.characterRole.supporting')}
                    </option>
                    <option value="mentioned">
                        {t('detailPanel.characterRole.mentioned')}
                    </option>
                </select>
                <button
                    type="button"
                    onClick={() => {
                        const updated = (plotPoint.characters ?? [])
                            .filter((c) => c.id !== char.id)
                            .map((c) => ({
                                id: c.id,
                                role: c.pivot?.role ?? 'key',
                            }));
                        onUpdate({ characters: updated });
                    }}
                    className="flex h-5 w-5 items-center justify-center rounded text-ink-faint hover:text-ink-soft"
                >
                    <X size={12} />
                </button>
            </div>
        </div>
    ))}

    {(() => {
        const taggedIds = new Set(
            (plotPoint.characters ?? []).map((c) => c.id),
        );
        const available = bookCharacters.filter(
            (c) => !taggedIds.has(c.id),
        );
        if (available.length === 0) return null;
        return (
            <select
                value=""
                onChange={(e) => {
                    const charId = Number(e.target.value);
                    if (!charId) return;
                    const updated = [
                        ...(plotPoint.characters ?? []).map((c) => ({
                            id: c.id,
                            role: c.pivot?.role ?? 'key',
                        })),
                        { id: charId, role: 'key' },
                    ];
                    onUpdate({ characters: updated });
                }}
                className="rounded border border-dashed border-border bg-transparent px-2 py-1.5 text-[12px] text-ink-muted"
            >
                <option value="">
                    {t('detailPanel.addCharacter')}
                </option>
                {available.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </select>
        );
    })()}
</div>
```

- [ ] **Step 4: Update plot/index.tsx to pass bookCharacters to DetailPanel**

In `resources/js/pages/plot/index.tsx`, add the `bookCharacters` prop to the `DetailPanel` usage:

```tsx
<DetailPanel
    plotPoint={selectedPlotPoint}
    storylines={storylines}
    acts={acts}
    connections={connections}
    bookCharacters={characters}
    onClose={() => setSelectedPlotPointId(null)}
    onUpdate={(data) =>
        handleUpdatePlotPoint(selectedPlotPoint.id, data)
    }
/>
```

- [ ] **Step 5: Verify build compiles**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build succeeds.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/plot/DetailPanel.tsx resources/js/pages/plot/index.tsx resources/js/i18n/*/plot.json
git commit -m "feat(plot): add characters section to DetailPanel with role management"
```

---

## Task 8: Update Pencil Designs

**Files:**
- Modify: `untitled.pen` — update Plot designs to reflect all 3 improvements

- [ ] **Step 1: Update "Plot — Empty State" to show 5 templates**

The empty state screen (`Awlfp`) currently shows 3 template cards. Add Save the Cat and Story Circle cards in the template row. Use Pencil batch_design to:
- Read the existing template cards in the empty state
- Add two more cards with the same styling
- Set the text to "Save the Cat" / "Story Circle" with their descriptions and beat counts

- [ ] **Step 2: Update "Plot — Wizard Modal (Step 1)" to show 5 options**

The wizard modal (`vGzDX`) lists 3 template options. Add Save the Cat and Story Circle options in the same radio-button style as the existing entries.

- [ ] **Step 3: Update "Plot Timeline — Refined" to show richer cards**

The timeline screen (`nhQcI`) has PlotPointCards in the swim lane grid. Update 2-3 of the existing cards to show:
- A description preview line below the title
- Character initial circles at the bottom
- Word count next to the type badge

- [ ] **Step 4: Take screenshots to verify**

Screenshot all three updated screens and verify they look correct.

- [ ] **Step 5: Commit**

```bash
git add untitled.pen
git commit -m "design(plot): update Pencil designs for templates, richer cards, characters"
```

---

## Task 9: Final Verification

- [ ] **Step 1: Run full plot test suite**

```bash
php artisan test --compact tests/Feature/PlotPointControllerTest.php tests/Feature/PlotSetupControllerTest.php tests/Feature/PlotPageTest.php tests/Feature/PlotPointConnectionControllerTest.php tests/Feature/PlotPointConnectionTest.php
```

Expected: All PASS

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Verify frontend build**

```bash
npm run build 2>&1 | tail -5
```

Expected: Build succeeds with no errors.

- [ ] **Step 4: Final commit if any Pint fixes**

```bash
git add -A && git commit -m "style: apply Pint formatting"
```

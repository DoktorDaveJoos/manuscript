# Plot Feature — Full Reimagining — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the Plot page into a strategic command center with swimlane timeline, list view, cause-and-effect connections, AI action sidebar, tension arc, and editor sidebar beats.

**Architecture:** Backend-first approach. New `plot_point_connections` table + `ConnectionType` enum. Expand `PlotController` for the page, add `PlotPointController` for CRUD, `PlotPointConnectionController` for connections, and `PlotAiController` for AI actions. Frontend is Inertia/React with four main components: SwimLaneTimeline, PlotPointList, DetailPanel, and AiActionSidebar.

**Tech Stack:** Laravel 12, Inertia.js v2, React 19, Tailwind CSS v4, dnd-kit for drag-and-drop, Laravel AI SDK for agents

**Design reference:** Paper artboards "8 — Plot (Timeline)", "8a — Plot (AI Sidebar Open)", "8b — Plot (Detail Panel)", "8c — Plot (Tension Arc)"

**Design doc:** `docs/plans/2026-03-09-plot-feature-design.md`

---

## Phase 1: Data Model

### Task 1: ConnectionType Enum

**Files:**

- Create: `app/Enums/ConnectionType.php`

**Step 1: Create the enum**

```php
<?php

namespace App\Enums;

enum ConnectionType: string
{
    case Causes = 'causes';
    case SetsUp = 'sets_up';
    case Resolves = 'resolves';
    case Contradicts = 'contradicts';
}
```

**Step 2: Commit**

```bash
git add app/Enums/ConnectionType.php
git commit -m "feat(plot): add ConnectionType enum"
```

### Task 2: PlotPointConnection Migration

**Files:**

- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_plot_point_connections_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_plot_point_connections_table --no-interaction`

**Step 2: Write migration**

```php
public function up(): void
{
    Schema::create('plot_point_connections', function (Blueprint $table) {
        $table->id();
        $table->foreignId('book_id')->constrained()->cascadeOnDelete();
        $table->foreignId('source_plot_point_id')->constrained('plot_points')->cascadeOnDelete();
        $table->foreignId('target_plot_point_id')->constrained('plot_points')->cascadeOnDelete();
        $table->string('type')->default('causes');
        $table->text('description')->nullable();
        $table->timestamps();

        $table->unique(['source_plot_point_id', 'target_plot_point_id']);
    });
}
```

**Step 3: Run migration on both databases**

Run: `php artisan migrate --no-interaction && DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`

**Step 4: Commit**

```bash
git add database/migrations/*create_plot_point_connections*
git commit -m "feat(plot): add plot_point_connections migration"
```

### Task 3: Add tension_score to plot_points

**Files:**

- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_tension_score_to_plot_points_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration add_tension_score_to_plot_points_table --no-interaction`

**Step 2: Write migration**

```php
public function up(): void
{
    Schema::table('plot_points', function (Blueprint $table) {
        $table->unsignedTinyInteger('tension_score')->nullable()->after('is_ai_derived');
    });
}

public function down(): void
{
    Schema::table('plot_points', function (Blueprint $table) {
        $table->dropColumn('tension_score');
    });
}
```

**Step 3: Run migration on both databases**

Run: `php artisan migrate --no-interaction && DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`

**Step 4: Commit**

```bash
git add database/migrations/*add_tension_score_to_plot_points*
git commit -m "feat(plot): add tension_score column to plot_points"
```

### Task 4: PlotPointConnection Model + Factory

**Files:**

- Create: `app/Models/PlotPointConnection.php`
- Create: `database/factories/PlotPointConnectionFactory.php`

**Step 1: Create model with factory**

Run: `php artisan make:model PlotPointConnection --factory --no-interaction`

**Step 2: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\ConnectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlotPointConnection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ConnectionType::class,
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PlotPoint::class, 'source_plot_point_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(PlotPoint::class, 'target_plot_point_id');
    }
}
```

**Step 3: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlotPointConnection> */
class PlotPointConnectionFactory extends Factory
{
    protected $model = PlotPointConnection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'source_plot_point_id' => PlotPoint::factory(),
            'target_plot_point_id' => PlotPoint::factory(),
            'type' => ConnectionType::Causes,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function setsUp(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::SetsUp]);
    }

    public function resolves(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::Resolves]);
    }

    public function contradicts(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::Contradicts]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Models/PlotPointConnection.php database/factories/PlotPointConnectionFactory.php
git commit -m "feat(plot): add PlotPointConnection model and factory"
```

### Task 5: Update PlotPoint Model with Connection Relationships

**Files:**

- Modify: `app/Models/PlotPoint.php`

**Step 1: Add relationships and tension_score cast**

Add to `casts()`:

```php
'tension_score' => 'integer',
```

Add relationship methods:

```php
public function outgoingConnections(): HasMany
{
    return $this->hasMany(PlotPointConnection::class, 'source_plot_point_id');
}

public function incomingConnections(): HasMany
{
    return $this->hasMany(PlotPointConnection::class, 'target_plot_point_id');
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` import.

**Step 2: Commit**

```bash
git add app/Models/PlotPoint.php
git commit -m "feat(plot): add connection relationships to PlotPoint"
```

### Task 6: Test Data Model

**Files:**

- Create: `tests/Feature/PlotPointConnectionTest.php`

**Step 1: Create the test**

Run: `php artisan make:test PlotPointConnectionTest --pest --no-interaction`

**Step 2: Write tests**

```php
<?php

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;

it('creates a connection between two plot points', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    $connection = PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::SetsUp,
        'description' => 'Foreshadowing',
    ]);

    expect($connection->source->id)->toBe($source->id)
        ->and($connection->target->id)->toBe($target->id)
        ->and($connection->type)->toBe(ConnectionType::SetsUp);
});

it('loads outgoing and incoming connections on a plot point', function () {
    $book = Book::factory()->create();
    $a = PlotPoint::factory()->create(['book_id' => $book->id]);
    $b = PlotPoint::factory()->create(['book_id' => $book->id]);
    $c = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $a->id,
        'target_plot_point_id' => $b->id,
        'type' => ConnectionType::Causes,
    ]);
    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $c->id,
        'target_plot_point_id' => $a->id,
        'type' => ConnectionType::SetsUp,
    ]);

    $a->load(['outgoingConnections', 'incomingConnections']);

    expect($a->outgoingConnections)->toHaveCount(1)
        ->and($a->incomingConnections)->toHaveCount(1);
});

it('prevents duplicate connections', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Causes,
    ]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Resolves,
    ]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('cascades delete when plot point is removed', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    PlotPointConnection::create([
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => ConnectionType::Causes,
    ]);

    $source->delete();

    expect(PlotPointConnection::count())->toBe(0);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=PlotPointConnectionTest`
Expected: All 4 tests PASS

**Step 4: Commit**

```bash
git add tests/Feature/PlotPointConnectionTest.php
git commit -m "test(plot): add PlotPointConnection model tests"
```

---

## Phase 2: Backend API — Plot Points CRUD

### Task 7: PlotPoint Form Requests

**Files:**

- Create: `app/Http/Requests/StorePlotPointRequest.php`
- Create: `app/Http/Requests/UpdatePlotPointRequest.php`

**Step 1: Create form requests**

Run: `php artisan make:request StorePlotPointRequest --no-interaction && php artisan make:request UpdatePlotPointRequest --no-interaction`

**Step 2: Write StorePlotPointRequest**

```php
<?php

namespace App\Http\Requests;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlotPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(PlotPointType::class)],
            'status' => ['sometimes', Rule::enum(PlotPointStatus::class)],
            'storyline_id' => ['nullable', 'exists:storylines,id'],
            'act_id' => ['nullable', 'exists:acts,id'],
            'intended_chapter_id' => ['nullable', 'exists:chapters,id'],
        ];
    }
}
```

**Step 3: Write UpdatePlotPointRequest**

```php
<?php

namespace App\Http\Requests;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlotPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::enum(PlotPointType::class)],
            'status' => ['sometimes', Rule::enum(PlotPointStatus::class)],
            'storyline_id' => ['nullable', 'exists:storylines,id'],
            'act_id' => ['nullable', 'exists:acts,id'],
            'intended_chapter_id' => ['nullable', 'exists:chapters,id'],
        ];
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Requests/StorePlotPointRequest.php app/Http/Requests/UpdatePlotPointRequest.php
git commit -m "feat(plot): add plot point form requests"
```

### Task 8: PlotPointController

**Files:**

- Create: `app/Http/Controllers/PlotPointController.php`

**Step 1: Create controller**

Run: `php artisan make:controller PlotPointController --no-interaction`

**Step 2: Write controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlotPointRequest;
use App\Http\Requests\UpdatePlotPointRequest;
use App\Models\Book;
use App\Models\PlotPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlotPointController extends Controller
{
    public function store(StorePlotPointRequest $request, Book $book): JsonResponse
    {
        $nextOrder = ($book->plotPoints()->max('sort_order') ?? -1) + 1;

        $plotPoint = $book->plotPoints()->create([
            ...$request->validated(),
            'sort_order' => $nextOrder,
        ]);

        $plotPoint->load(['storyline', 'act', 'intendedChapter']);

        return response()->json($plotPoint, 201);
    }

    public function update(UpdatePlotPointRequest $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $plotPoint->update($request->validated());
        $plotPoint->load(['storyline', 'act', 'intendedChapter']);

        return response()->json($plotPoint);
    }

    public function destroy(Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $plotPoint->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request, Book $book): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:plot_points,id'],
            'order.*.storyline_id' => ['nullable', 'integer'],
            'order.*.intended_chapter_id' => ['nullable', 'integer'],
            'order.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('order') as $item) {
            PlotPoint::where('id', $item['id'])->update([
                'storyline_id' => $item['storyline_id'],
                'intended_chapter_id' => $item['intended_chapter_id'],
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, Book $book, PlotPoint $plotPoint): JsonResponse
    {
        $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\PlotPointStatus::class)],
        ]);

        $plotPoint->update(['status' => $request->input('status')]);

        return response()->json($plotPoint);
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/PlotPointController.php
git commit -m "feat(plot): add PlotPointController with CRUD + reorder"
```

### Task 9: PlotPointConnectionController

**Files:**

- Create: `app/Http/Controllers/PlotPointConnectionController.php`
- Create: `app/Http/Requests/StorePlotPointConnectionRequest.php`

**Step 1: Create files**

Run: `php artisan make:controller PlotPointConnectionController --no-interaction && php artisan make:request StorePlotPointConnectionRequest --no-interaction`

**Step 2: Write the form request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\ConnectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlotPointConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_plot_point_id' => ['required', 'exists:plot_points,id'],
            'target_plot_point_id' => ['required', 'exists:plot_points,id', 'different:source_plot_point_id'],
            'type' => ['required', Rule::enum(ConnectionType::class)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

**Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlotPointConnectionRequest;
use App\Models\Book;
use App\Models\PlotPointConnection;
use Illuminate\Http\JsonResponse;

class PlotPointConnectionController extends Controller
{
    public function store(StorePlotPointConnectionRequest $request, Book $book): JsonResponse
    {
        $connection = $book->plotPointConnections()->create($request->validated());
        $connection->load(['source', 'target']);

        return response()->json($connection, 201);
    }

    public function destroy(Book $book, PlotPointConnection $plotPointConnection): JsonResponse
    {
        $plotPointConnection->delete();

        return response()->json(null, 204);
    }
}
```

**Step 4: Add `plotPointConnections` relationship to Book model**

In `app/Models/Book.php`, add:

```php
public function plotPointConnections(): HasMany
{
    return $this->hasMany(PlotPointConnection::class);
}
```

**Step 5: Commit**

```bash
git add app/Http/Controllers/PlotPointConnectionController.php app/Http/Requests/StorePlotPointConnectionRequest.php app/Models/Book.php
git commit -m "feat(plot): add PlotPointConnectionController + Book relationship"
```

### Task 10: Expand PlotController

**Files:**

- Modify: `app/Http/Controllers/PlotController.php`

**Step 1: Update index to load all needed data**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class PlotController extends Controller
{
    public function index(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'acts' => fn ($q) => $q->orderBy('sort_order'),
            'acts.chapters' => fn ($q) => $q->orderBy('reader_order')
                ->select('id', 'book_id', 'act_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'tension_score'),
            'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
            'plotPoints.outgoingConnections',
            'plotPoints.incomingConnections',
            'plotPointConnections.source',
            'plotPointConnections.target',
        ]);

        return Inertia::render('plot/index', [
            'book' => $book,
            'storylines' => $book->storylines,
            'acts' => $book->acts,
            'plotPoints' => $book->plotPoints,
            'connections' => $book->plotPointConnections,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/PlotController.php
git commit -m "feat(plot): expand PlotController with full data loading"
```

### Task 11: Register Routes

**Files:**

- Modify: `routes/web.php`

**Step 1: Add plot point and connection routes**

After the existing plot route (`Route::get('/books/{book}/plot', ...)`), add:

```php
Route::post('/books/{book}/plot-points', [PlotPointController::class, 'store'])->name('plotPoints.store');
Route::patch('/books/{book}/plot-points/{plotPoint}', [PlotPointController::class, 'update'])->name('plotPoints.update');
Route::delete('/books/{book}/plot-points/{plotPoint}', [PlotPointController::class, 'destroy'])->name('plotPoints.destroy');
Route::post('/books/{book}/plot-points/reorder', [PlotPointController::class, 'reorder'])->name('plotPoints.reorder');
Route::patch('/books/{book}/plot-points/{plotPoint}/status', [PlotPointController::class, 'updateStatus'])->name('plotPoints.updateStatus');

Route::post('/books/{book}/plot-connections', [PlotPointConnectionController::class, 'store'])->name('plotConnections.store');
Route::delete('/books/{book}/plot-connections/{plotPointConnection}', [PlotPointConnectionController::class, 'destroy'])->name('plotConnections.destroy');
```

Add the use statements at the top:

```php
use App\Http\Controllers\PlotPointController;
use App\Http\Controllers\PlotPointConnectionController;
```

**Step 2: Run Wayfinder generation**

Run: `php artisan wayfinder:generate --no-interaction`

**Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(plot): register plot point and connection routes"
```

### Task 12: Test PlotPointController

**Files:**

- Create: `tests/Feature/PlotPointControllerTest.php`

**Step 1: Create test file**

Run: `php artisan make:test PlotPointControllerTest --pest --no-interaction`

**Step 2: Write tests**

```php
<?php

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotPoint;
use App\Models\Storyline;

it('creates a plot point', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $act = Act::factory()->create(['book_id' => $book->id]);

    $response = $this->postJson(route('plotPoints.store', $book), [
        'title' => 'The reveal',
        'description' => 'Jonas discovers the truth',
        'type' => 'turning_point',
        'storyline_id' => $storyline->id,
        'act_id' => $act->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('title', 'The reveal')
        ->assertJsonPath('type', 'turning_point');

    $this->assertDatabaseHas('plot_points', [
        'book_id' => $book->id,
        'title' => 'The reveal',
    ]);
});

it('updates a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'title' => 'Old title']);

    $response = $this->patchJson(route('plotPoints.update', [$book, $plotPoint]), [
        'title' => 'New title',
        'status' => 'fulfilled',
    ]);

    $response->assertOk()->assertJsonPath('title', 'New title');
    expect($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});

it('deletes a plot point', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->deleteJson(route('plotPoints.destroy', [$book, $plotPoint]))
        ->assertNoContent();

    $this->assertDatabaseMissing('plot_points', ['id' => $plotPoint->id]);
});

it('reorders plot points', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter1 = Chapter::factory()->create(['book_id' => $book->id]);
    $chapter2 = Chapter::factory()->create(['book_id' => $book->id]);

    $a = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 0, 'intended_chapter_id' => $chapter1->id]);
    $b = PlotPoint::factory()->create(['book_id' => $book->id, 'sort_order' => 1, 'intended_chapter_id' => $chapter1->id]);

    $response = $this->postJson(route('plotPoints.reorder', $book), [
        'order' => [
            ['id' => $a->id, 'storyline_id' => $storyline->id, 'intended_chapter_id' => $chapter2->id, 'sort_order' => 1],
            ['id' => $b->id, 'storyline_id' => $storyline->id, 'intended_chapter_id' => $chapter1->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertOk();
    expect($a->fresh()->intended_chapter_id)->toBe($chapter2->id)
        ->and($b->fresh()->sort_order)->toBe(0);
});

it('cycles plot point status', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->create(['book_id' => $book->id, 'status' => PlotPointStatus::Planned]);

    $this->patchJson(route('plotPoints.updateStatus', [$book, $plotPoint]), [
        'status' => 'fulfilled',
    ])->assertOk();

    expect($plotPoint->fresh()->status)->toBe(PlotPointStatus::Fulfilled);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=PlotPointControllerTest`
Expected: All 5 tests PASS

**Step 4: Commit**

```bash
git add tests/Feature/PlotPointControllerTest.php
git commit -m "test(plot): add PlotPointController tests"
```

### Task 13: Test PlotPointConnectionController

**Files:**

- Create: `tests/Feature/PlotPointConnectionControllerTest.php`

**Step 1: Create test file**

Run: `php artisan make:test PlotPointConnectionControllerTest --pest --no-interaction`

**Step 2: Write tests**

```php
<?php

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;

it('creates a connection between plot points', function () {
    $book = Book::factory()->create();
    $source = PlotPoint::factory()->create(['book_id' => $book->id]);
    $target = PlotPoint::factory()->create(['book_id' => $book->id]);

    $response = $this->postJson(route('plotConnections.store', $book), [
        'source_plot_point_id' => $source->id,
        'target_plot_point_id' => $target->id,
        'type' => 'sets_up',
        'description' => 'Foreshadows the twist',
    ]);

    $response->assertCreated()
        ->assertJsonPath('type', 'sets_up');

    $this->assertDatabaseHas('plot_point_connections', [
        'book_id' => $book->id,
        'source_plot_point_id' => $source->id,
    ]);
});

it('rejects self-connection', function () {
    $book = Book::factory()->create();
    $point = PlotPoint::factory()->create(['book_id' => $book->id]);

    $this->postJson(route('plotConnections.store', $book), [
        'source_plot_point_id' => $point->id,
        'target_plot_point_id' => $point->id,
        'type' => 'causes',
    ])->assertUnprocessable();
});

it('deletes a connection', function () {
    $book = Book::factory()->create();
    $connection = PlotPointConnection::factory()->create(['book_id' => $book->id]);

    $this->deleteJson(route('plotConnections.destroy', [$book, $connection]))
        ->assertNoContent();

    $this->assertDatabaseMissing('plot_point_connections', ['id' => $connection->id]);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=PlotPointConnectionControllerTest`
Expected: All 3 tests PASS

**Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 5: Commit**

```bash
git add tests/Feature/PlotPointConnectionControllerTest.php
git commit -m "test(plot): add PlotPointConnectionController tests"
```

---

## Phase 3: Frontend — TypeScript Types + Plot Page Layout

### Task 14: Update TypeScript Types

**Files:**

- Modify: `resources/js/types/models.ts`

**Step 1: Add new types**

Add after the existing `PlotPointStatus` type:

```typescript
export type ConnectionType = 'causes' | 'sets_up' | 'resolves' | 'contradicts';
```

Add after the `PlotPoint` type:

```typescript
export type PlotPointConnection = {
    id: number;
    book_id: number;
    source_plot_point_id: number;
    target_plot_point_id: number;
    type: ConnectionType;
    description: string | null;
    created_at: string;
    updated_at: string;
    source?: PlotPoint;
    target?: PlotPoint;
};
```

Add `tension_score` to the `PlotPoint` type:

```typescript
tension_score: number | null;
```

Add connection relations to `PlotPoint`:

```typescript
outgoing_connections?: PlotPointConnection[];
incoming_connections?: PlotPointConnection[];
```

**Step 2: Commit**

```bash
git add resources/js/types/models.ts
git commit -m "feat(plot): add PlotPointConnection TypeScript types"
```

### Task 15: Plot Page Layout with Tabs

**Files:**

- Modify: `resources/js/pages/plot/index.tsx`

**Step 1: Rewrite the plot page with tab layout**

Reference the Paper design "8 — Plot (Timeline)" for the layout structure. The page should have:

- Left sidebar (existing `Sidebar` component)
- Main content area with:
    - Header bar: "Timeline" | "List" tabs (left), "All storylines" filter + "+" button + AI sidebar toggle (right)
    - Content area that renders either `SwimLaneTimeline` or `PlotPointList` based on active tab
- Right AI sidebar (collapsed by default, hidden when AI disabled)

```tsx
import Sidebar from '@/components/editor/Sidebar';
import type {
    Act,
    Book,
    PlotPoint,
    PlotPointConnection,
    Storyline,
} from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useAiFeatures } from '@/hooks/useAiFeatures';

type PlotPageProps = {
    book: Book & { storylines: Storyline[] };
    storylines: Storyline[];
    acts: (Act & {
        chapters: {
            id: number;
            title: string;
            reader_order: number;
            act_id: number;
            storyline_id: number;
            tension_score: number | null;
        }[];
    })[];
    plotPoints: PlotPoint[];
    connections: PlotPointConnection[];
};

export default function Plot({
    book,
    storylines,
    acts,
    plotPoints,
    connections,
}: PlotPageProps) {
    const [activeTab, setActiveTab] = useState<'timeline' | 'list'>('timeline');
    const [selectedPlotPoint, setSelectedPlotPoint] =
        useState<PlotPoint | null>(null);
    const [storylineFilter, setStorylineFilter] = useState<number | null>(null);
    const ai = useAiFeatures();

    const filteredPlotPoints = storylineFilter
        ? plotPoints.filter((pp) => pp.storyline_id === storylineFilter)
        : plotPoints;

    return (
        <>
            <Head title={`Plot — ${book.title}`} />
            <div className="flex h-screen overflow-hidden bg-[#FAFAF7]">
                <Sidebar book={book} storylines={storylines} />

                <main className="flex min-w-0 flex-1 flex-col overflow-hidden">
                    {/* Header bar */}
                    <div className="flex items-center justify-between border-b border-[#ECEAE4] bg-white px-6 py-3">
                        <div className="flex items-center gap-6">
                            <button
                                onClick={() => setActiveTab('timeline')}
                                className={`pb-0.5 text-sm font-semibold ${activeTab === 'timeline' ? 'border-b-2 border-[#1A1A1A] text-[#1A1A1A]' : 'text-[#8A857D]'}`}
                            >
                                Timeline
                            </button>
                            <button
                                onClick={() => setActiveTab('list')}
                                className={`pb-0.5 text-sm font-semibold ${activeTab === 'list' ? 'border-b-2 border-[#1A1A1A] text-[#1A1A1A]' : 'text-[#8A857D]'}`}
                            >
                                List
                            </button>
                        </div>

                        <div className="flex items-center gap-3">
                            <select
                                value={storylineFilter ?? ''}
                                onChange={(e) =>
                                    setStorylineFilter(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                                className="rounded border border-[#ECEAE4] bg-white px-3 py-1.5 text-xs text-[#5A574F]"
                            >
                                <option value="">All storylines</option>
                                {storylines.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                            {/* + button placeholder — will be wired in Task 18 */}
                            {ai.visible && (
                                <button className="rounded p-1.5 text-[#8A857D] hover:bg-[#F0EEEA]">
                                    {/* AI sidebar toggle icon */}
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 18 18"
                                        fill="none"
                                    >
                                        <rect
                                            x="1"
                                            y="1"
                                            width="16"
                                            height="16"
                                            rx="2"
                                            stroke="currentColor"
                                            strokeWidth="1.5"
                                        />
                                        <line
                                            x1="12"
                                            y1="1"
                                            x2="12"
                                            y2="17"
                                            stroke="currentColor"
                                            strokeWidth="1.5"
                                        />
                                    </svg>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Content area — placeholder for timeline/list */}
                    <div className="flex-1 overflow-auto">
                        {activeTab === 'timeline' ? (
                            <div className="p-6 text-sm text-[#8A857D]">
                                Timeline view — Task 17
                            </div>
                        ) : (
                            <div className="p-6 text-sm text-[#8A857D]">
                                List view — Task 22
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
```

**Step 2: Verify it renders**

Run: `npm run build`

**Step 3: Commit**

```bash
git add resources/js/pages/plot/index.tsx
git commit -m "feat(plot): scaffold Plot page layout with tab switching"
```

---

## Phase 4: Swimlane Timeline

### Task 16: Build the Timeline Grid Data Structure

**Files:**

- Create: `resources/js/lib/plot-utils.ts`

**Step 1: Create utility for building the grid**

This utility maps plot points into a storyline × chapter grid structure that the timeline component will consume.

```typescript
import type { Act, PlotPoint, Storyline } from '@/types/models';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
};

type ActGroup = Act & { chapters: ChapterColumn[] };

export type GridCell = {
    storylineId: number;
    chapterId: number;
    plotPoints: PlotPoint[];
};

export function buildTimelineGrid(
    acts: ActGroup[],
    storylines: Storyline[],
    plotPoints: PlotPoint[],
): { grid: Map<string, GridCell>; allChapters: ChapterColumn[] } {
    const grid = new Map<string, GridCell>();
    const allChapters: ChapterColumn[] = [];

    for (const act of acts) {
        for (const chapter of act.chapters) {
            allChapters.push(chapter);
        }
    }

    for (const storyline of storylines) {
        for (const chapter of allChapters) {
            const key = `${storyline.id}-${chapter.id}`;
            grid.set(key, {
                storylineId: storyline.id,
                chapterId: chapter.id,
                plotPoints: [],
            });
        }
    }

    for (const pp of plotPoints) {
        if (pp.storyline_id && pp.intended_chapter_id) {
            const key = `${pp.storyline_id}-${pp.intended_chapter_id}`;
            const cell = grid.get(key);
            if (cell) {
                cell.plotPoints.push(pp);
            }
        }
    }

    return { grid, allChapters };
}

export function cellKey(storylineId: number, chapterId: number): string {
    return `${storylineId}-${chapterId}`;
}
```

**Step 2: Commit**

```bash
git add resources/js/lib/plot-utils.ts
git commit -m "feat(plot): add timeline grid utility"
```

### Task 17: SwimLaneTimeline Component

**Files:**

- Create: `resources/js/components/plot/SwimLaneTimeline.tsx`
- Modify: `resources/js/pages/plot/index.tsx` — wire in the component

**Step 1: Build the SwimLaneTimeline**

Follow the Paper design "8 — Plot (Timeline)". Structure:

- Act headers row with color bars (gold `#C8B88A`, blue `#8AB0C8`, green `#A3C4A0`)
- Chapter sub-headers row
- Storyline rows with cells containing plot point cards
- Empty cells are clickable to create new plot points

```tsx
import { buildTimelineGrid, cellKey, type GridCell } from '@/lib/plot-utils';
import type {
    Act,
    PlotPoint,
    PlotPointConnection,
    Storyline,
} from '@/types/models';
import PlotPointCard from './PlotPointCard';

type ChapterColumn = {
    id: number;
    title: string;
    reader_order: number;
    act_id: number;
    storyline_id: number;
    tension_score: number | null;
};

type Props = {
    acts: (Act & { chapters: ChapterColumn[] })[];
    storylines: Storyline[];
    plotPoints: PlotPoint[];
    connections: PlotPointConnection[];
    onSelectPlotPoint: (pp: PlotPoint) => void;
    onCreatePlotPoint: (storylineId: number, chapterId: number) => void;
};

const ACT_COLORS: Record<number, string> = {
    0: '#C8B88A',
    1: '#8AB0C8',
    2: '#A3C4A0',
};

export default function SwimLaneTimeline({
    acts,
    storylines,
    plotPoints,
    connections,
    onSelectPlotPoint,
    onCreatePlotPoint,
}: Props) {
    const { grid, allChapters } = buildTimelineGrid(
        acts,
        storylines,
        plotPoints,
    );
    const COL_W = 160;
    const LABEL_W = 120;

    return (
        <div className="overflow-auto">
            <div
                className="inline-flex flex-col"
                style={{ minWidth: LABEL_W + allChapters.length * COL_W }}
            >
                {/* Act headers */}
                <div className="flex" style={{ paddingLeft: LABEL_W }}>
                    {acts.map((act, i) => (
                        <div
                            key={act.id}
                            className="flex items-center gap-2 border-b border-[#ECEAE4] px-3 py-2"
                            style={{ width: act.chapters.length * COL_W }}
                        >
                            <div
                                className="h-3.5 w-1 rounded-sm"
                                style={{
                                    backgroundColor: ACT_COLORS[i] ?? '#C8B88A',
                                }}
                            />
                            <span className="text-[11px] font-semibold tracking-wide text-[#5A574F] uppercase">
                                Act {act.number} — {act.title}
                            </span>
                        </div>
                    ))}
                </div>

                {/* Chapter sub-headers */}
                <div
                    className="flex border-b border-[#ECEAE4]"
                    style={{ paddingLeft: LABEL_W }}
                >
                    {allChapters.map((ch) => (
                        <div
                            key={ch.id}
                            className="px-3 py-1.5 text-[11px] text-[#8A857D]"
                            style={{ width: COL_W }}
                        >
                            Ch. {ch.reader_order + 1}
                        </div>
                    ))}
                </div>

                {/* Storyline rows */}
                {storylines.map((storyline) => (
                    <div
                        key={storyline.id}
                        className="flex border-b border-[#F0EEEA]"
                    >
                        {/* Storyline label */}
                        <div
                            className="flex items-start gap-2 px-3 py-3"
                            style={{ width: LABEL_W, flexShrink: 0 }}
                        >
                            <div
                                className="mt-0.5 h-2 w-2 rounded-full"
                                style={{
                                    backgroundColor:
                                        storyline.color ?? '#8A857D',
                                }}
                            />
                            <span className="text-[11px] font-semibold tracking-wide text-[#5A574F] uppercase">
                                {storyline.name}
                            </span>
                        </div>

                        {/* Cells */}
                        {allChapters.map((ch) => {
                            const cell = grid.get(cellKey(storyline.id, ch.id));
                            return (
                                <div
                                    key={ch.id}
                                    className="min-h-[80px] cursor-pointer border-l border-[#F0EEEA] p-1.5 hover:bg-[#FAFAF7]"
                                    style={{ width: COL_W }}
                                    onClick={() => {
                                        if (!cell?.plotPoints.length) {
                                            onCreatePlotPoint(
                                                storyline.id,
                                                ch.id,
                                            );
                                        }
                                    }}
                                >
                                    <div className="flex flex-col gap-1">
                                        {cell?.plotPoints.map((pp) => (
                                            <PlotPointCard
                                                key={pp.id}
                                                plotPoint={pp}
                                                onClick={() =>
                                                    onSelectPlotPoint(pp)
                                                }
                                            />
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/components/plot/SwimLaneTimeline.tsx
git commit -m "feat(plot): add SwimLaneTimeline component"
```

### Task 18: PlotPointCard Component

**Files:**

- Create: `resources/js/components/plot/PlotPointCard.tsx`

**Step 1: Build the card**

Follow Paper design — compact card with title + type badge. Badge colors:

- Setup: `bg-[#EDE8F5] text-[#6B5A8E]`
- Conflict: `bg-[#F5E8E8] text-[#8E5A5A]`
- Turning point: `bg-[#F5EDE0] text-[#8A7A5A]`
- Resolution: `bg-[#E8F0E8] text-[#5A8E5A]`
- Worldbuilding: `bg-[#E8EDF5] text-[#5A6B8E]`

Status dot: `#6DBB7B` (fulfilled), `#D4A843` (planned), `#B0A99F` (abandoned)

```tsx
import type { PlotPoint } from '@/types/models';

const TYPE_STYLES: Record<string, string> = {
    setup: 'bg-[#EDE8F5] text-[#6B5A8E]',
    conflict: 'bg-[#F5E8E8] text-[#8E5A5A]',
    turning_point: 'bg-[#F5EDE0] text-[#8A7A5A]',
    resolution: 'bg-[#E8F0E8] text-[#5A8E5A]',
    worldbuilding: 'bg-[#E8EDF5] text-[#5A6B8E]',
};

const TYPE_LABELS: Record<string, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning point',
    resolution: 'Resolution',
    worldbuilding: 'Worldbuilding',
};

const STATUS_COLORS: Record<string, string> = {
    planned: '#D4A843',
    fulfilled: '#6DBB7B',
    abandoned: '#B0A99F',
};

type Props = {
    plotPoint: PlotPoint;
    onClick: () => void;
};

export default function PlotPointCard({ plotPoint, onClick }: Props) {
    return (
        <button
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className="w-full rounded border border-[#ECEAE4] bg-white px-2.5 py-2 text-left shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow hover:shadow-[0_2px_4px_rgba(0,0,0,0.08)]"
        >
            <div className="flex items-start gap-1.5">
                <div
                    className="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    style={{
                        backgroundColor:
                            STATUS_COLORS[plotPoint.status] ?? '#B0A99F',
                    }}
                />
                <span className="text-xs leading-tight font-medium text-[#2D2A26]">
                    {plotPoint.title}
                </span>
            </div>
            <div className="mt-1.5">
                <span
                    className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[plotPoint.type] ?? ''}`}
                >
                    {TYPE_LABELS[plotPoint.type] ?? plotPoint.type}
                </span>
            </div>
        </button>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/components/plot/PlotPointCard.tsx
git commit -m "feat(plot): add PlotPointCard component"
```

### Task 19: Wire Timeline into Plot Page + Quick Create

**Files:**

- Modify: `resources/js/pages/plot/index.tsx`

**Step 1: Import and render SwimLaneTimeline in the timeline tab**

Replace the timeline placeholder with:

```tsx
import SwimLaneTimeline from '@/components/plot/SwimLaneTimeline';
```

Replace the timeline div with:

```tsx
<SwimLaneTimeline
    acts={acts}
    storylines={storylines}
    plotPoints={filteredPlotPoints}
    connections={connections}
    onSelectPlotPoint={setSelectedPlotPoint}
    onCreatePlotPoint={handleCreatePlotPoint}
/>
```

Add a `handleCreatePlotPoint` function that uses Wayfinder to POST to the store endpoint:

```tsx
import { store as storePlotPoint } from '@/actions/App/Http/Controllers/PlotPointController';

function handleCreatePlotPoint(storylineId: number, chapterId: number) {
    router.post(
        storePlotPoint({ book: book.id }),
        {
            title: 'New beat',
            type: 'setup',
            storyline_id: storylineId,
            intended_chapter_id: chapterId,
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                router.reload({ only: ['plotPoints', 'connections'] }),
        },
    );
}
```

Note: Wayfinder actions are auto-generated. After adding routes in Task 11 and running `php artisan wayfinder:generate`, the import paths will be available. If the import doesn't exist yet, use `router.post(route('plotPoints.store', book.id), ...)` as fallback. Check `resources/js/actions/` for the generated file path.

**Step 2: Build**

Run: `npm run build`

**Step 3: Commit**

```bash
git add resources/js/pages/plot/index.tsx
git commit -m "feat(plot): wire SwimLaneTimeline into Plot page"
```

---

## Phase 5: Detail Panel

### Task 20: DetailPanel Component

**Files:**

- Create: `resources/js/components/plot/DetailPanel.tsx`

**Step 1: Build the panel**

Follow Paper design "8b — Plot (Detail Panel)". A slide-out panel on the right (320px wide) showing:

- Title (editable)
- Description (editable textarea)
- Type badge + Status badge
- Metadata: Storyline, Act, Chapter
- Connections section (incoming/outgoing)
- "Jump to chapter" button

```tsx
import type {
    Act,
    PlotPoint,
    PlotPointConnection,
    Storyline,
} from '@/types/models';
import { router } from '@inertiajs/react';
import { X } from '@phosphor-icons/react';

type Props = {
    plotPoint: PlotPoint;
    storylines: Storyline[];
    acts: Act[];
    connections: PlotPointConnection[];
    onClose: () => void;
    onUpdate: (data: Partial<PlotPoint>) => void;
};

const TYPE_OPTIONS = [
    { value: 'setup', label: 'Setup' },
    { value: 'conflict', label: 'Conflict' },
    { value: 'turning_point', label: 'Turning Point' },
    { value: 'resolution', label: 'Resolution' },
    { value: 'worldbuilding', label: 'Worldbuilding' },
];

const STATUS_OPTIONS = [
    { value: 'planned', label: 'Planned', color: '#D4A843' },
    { value: 'fulfilled', label: 'Fulfilled', color: '#6DBB7B' },
    { value: 'abandoned', label: 'Abandoned', color: '#B0A99F' },
];

export default function DetailPanel({
    plotPoint,
    storylines,
    acts,
    connections,
    onClose,
    onUpdate,
}: Props) {
    const incoming = connections.filter(
        (c) => c.target_plot_point_id === plotPoint.id,
    );
    const outgoing = connections.filter(
        (c) => c.source_plot_point_id === plotPoint.id,
    );

    return (
        <div className="flex h-full w-[320px] flex-shrink-0 flex-col border-l border-[#ECEAE4] bg-white">
            {/* Header */}
            <div className="flex items-center justify-between border-b border-[#ECEAE4] px-4 py-3">
                <span className="text-xs font-semibold tracking-wide text-[#8A857D] uppercase">
                    Plot Point
                </span>
                <button
                    onClick={onClose}
                    className="text-[#8A857D] hover:text-[#5A574F]"
                >
                    <X size={16} />
                </button>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4">
                {/* Title */}
                <input
                    defaultValue={plotPoint.title}
                    onBlur={(e) => onUpdate({ title: e.target.value })}
                    className="mb-3 w-full text-base font-semibold text-[#1A1A1A] outline-none"
                />

                {/* Description */}
                <textarea
                    defaultValue={plotPoint.description ?? ''}
                    onBlur={(e) => onUpdate({ description: e.target.value })}
                    placeholder="Add a description..."
                    className="mb-4 w-full resize-none text-sm text-[#5A574F] outline-none placeholder:text-[#B0A99F]"
                    rows={3}
                />

                {/* Type & Status */}
                <div className="mb-4 flex gap-2">
                    <select
                        value={plotPoint.type}
                        onChange={(e) =>
                            onUpdate({
                                type: e.target.value as PlotPoint['type'],
                            })
                        }
                        className="rounded border border-[#ECEAE4] px-2 py-1 text-xs text-[#5A574F]"
                    >
                        {TYPE_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={plotPoint.status}
                        onChange={(e) =>
                            onUpdate({
                                status: e.target.value as PlotPoint['status'],
                            })
                        }
                        className="rounded border border-[#ECEAE4] px-2 py-1 text-xs text-[#5A574F]"
                    >
                        {STATUS_OPTIONS.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Connections */}
                {(incoming.length > 0 || outgoing.length > 0) && (
                    <div className="mb-4 border-t border-[#F0EEEA] pt-3">
                        <span className="mb-2 block text-[10px] font-semibold tracking-wider text-[#8A857D] uppercase">
                            Connections
                        </span>
                        {incoming.map((c) => (
                            <div
                                key={c.id}
                                className="mb-1 text-xs text-[#5A574F]"
                            >
                                <span className="text-[#8A857D]">
                                    {c.type === 'sets_up'
                                        ? 'Set up by'
                                        : 'Caused by'}
                                    :
                                </span>{' '}
                                {c.source?.title}
                            </div>
                        ))}
                        {outgoing.map((c) => (
                            <div
                                key={c.id}
                                className="mb-1 text-xs text-[#5A574F]"
                            >
                                <span className="text-[#8A857D]">
                                    {c.type === 'resolves'
                                        ? 'Resolves'
                                        : 'Leads to'}
                                    :
                                </span>{' '}
                                {c.target?.title}
                            </div>
                        ))}
                    </div>
                )}

                {/* Jump to chapter */}
                {plotPoint.intended_chapter_id && (
                    <button
                        onClick={() =>
                            router.visit(
                                `/books/${plotPoint.book_id}/chapters/${plotPoint.intended_chapter_id}`,
                            )
                        }
                        className="mt-2 w-full rounded border border-[#ECEAE4] px-3 py-2 text-xs font-medium text-[#5A574F] hover:bg-[#FAFAF7]"
                    >
                        Jump to chapter
                    </button>
                )}
            </div>
        </div>
    );
}
```

**Step 2: Wire into plot page**

In `resources/js/pages/plot/index.tsx`, import DetailPanel and render it conditionally when `selectedPlotPoint` is set:

```tsx
import DetailPanel from '@/components/plot/DetailPanel';

// Inside the main flex container, after the content area:
{
    selectedPlotPoint && (
        <DetailPanel
            plotPoint={selectedPlotPoint}
            storylines={storylines}
            acts={acts}
            connections={connections}
            onClose={() => setSelectedPlotPoint(null)}
            onUpdate={(data) =>
                handleUpdatePlotPoint(selectedPlotPoint.id, data)
            }
        />
    );
}
```

Add `handleUpdatePlotPoint`:

```tsx
function handleUpdatePlotPoint(id: number, data: Partial<PlotPoint>) {
    router.patch(route('plotPoints.update', [book.id, id]), data, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['plotPoints'] });
            setSelectedPlotPoint(null);
        },
    });
}
```

**Step 3: Build**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/js/components/plot/DetailPanel.tsx resources/js/pages/plot/index.tsx
git commit -m "feat(plot): add DetailPanel component"
```

---

## Phase 6: Plot Points List View

### Task 21: PlotPointList Component

**Files:**

- Create: `resources/js/components/plot/PlotPointList.tsx`

**Step 1: Build the list view**

Grouped by acts. Each plot point shows title, description preview, type badge, status dot, and linked chapter.

```tsx
import type { Act, PlotPoint, Storyline } from '@/types/models';

const TYPE_STYLES: Record<string, string> = {
    setup: 'bg-[#EDE8F5] text-[#6B5A8E]',
    conflict: 'bg-[#F5E8E8] text-[#8E5A5A]',
    turning_point: 'bg-[#F5EDE0] text-[#8A7A5A]',
    resolution: 'bg-[#E8F0E8] text-[#5A8E5A]',
    worldbuilding: 'bg-[#E8EDF5] text-[#5A6B8E]',
};

const TYPE_LABELS: Record<string, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning point',
    resolution: 'Resolution',
    worldbuilding: 'Worldbuilding',
};

const STATUS_COLORS: Record<string, string> = {
    planned: '#D4A843',
    fulfilled: '#6DBB7B',
    abandoned: '#B0A99F',
};

type Props = {
    acts: (Act & { chapters: { id: number; title: string }[] })[];
    plotPoints: PlotPoint[];
    storylines: Storyline[];
    onSelectPlotPoint: (pp: PlotPoint) => void;
};

export default function PlotPointList({
    acts,
    plotPoints,
    storylines,
    onSelectPlotPoint,
}: Props) {
    const getStorylineName = (id: number | null) =>
        storylines.find((s) => s.id === id)?.name ?? '—';

    return (
        <div className="mx-auto max-w-[720px] px-6 py-8">
            {acts.map((act) => {
                const actPoints = plotPoints.filter(
                    (pp) => pp.act_id === act.id,
                );
                if (actPoints.length === 0) return null;

                return (
                    <div key={act.id} className="mb-8">
                        <h3 className="mb-3 text-[11px] font-semibold tracking-wide text-[#8A857D] uppercase">
                            Act {act.number} — {act.title}
                        </h3>
                        <div className="flex flex-col gap-2">
                            {actPoints.map((pp) => (
                                <button
                                    key={pp.id}
                                    onClick={() => onSelectPlotPoint(pp)}
                                    className="flex items-start gap-3 rounded-lg border border-[#ECEAE4] bg-white px-4 py-3 text-left transition-shadow hover:shadow-sm"
                                >
                                    <div
                                        className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full"
                                        style={{
                                            backgroundColor:
                                                STATUS_COLORS[pp.status],
                                        }}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium text-[#1A1A1A]">
                                                {pp.title}
                                            </span>
                                            <span
                                                className={`rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[pp.type]}`}
                                            >
                                                {TYPE_LABELS[pp.type]}
                                            </span>
                                        </div>
                                        {pp.description && (
                                            <p className="mt-1 line-clamp-1 text-xs text-[#8A857D]">
                                                {pp.description}
                                            </p>
                                        )}
                                        <div className="mt-1.5 flex gap-3 text-[10px] text-[#B0A99F]">
                                            <span>
                                                {getStorylineName(
                                                    pp.storyline_id,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                );
            })}

            {/* Unassigned plot points (no act) */}
            {plotPoints.filter((pp) => !pp.act_id).length > 0 && (
                <div className="mb-8">
                    <h3 className="mb-3 text-[11px] font-semibold tracking-wide text-[#8A857D] uppercase">
                        Unassigned
                    </h3>
                    <div className="flex flex-col gap-2">
                        {plotPoints
                            .filter((pp) => !pp.act_id)
                            .map((pp) => (
                                <button
                                    key={pp.id}
                                    onClick={() => onSelectPlotPoint(pp)}
                                    className="flex items-start gap-3 rounded-lg border border-[#ECEAE4] bg-white px-4 py-3 text-left hover:shadow-sm"
                                >
                                    <div
                                        className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full"
                                        style={{
                                            backgroundColor:
                                                STATUS_COLORS[pp.status],
                                        }}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <span className="text-sm font-medium text-[#1A1A1A]">
                                            {pp.title}
                                        </span>
                                        <span
                                            className={`ml-2 rounded px-1.5 py-0.5 text-[10px] font-medium ${TYPE_STYLES[pp.type]}`}
                                        >
                                            {TYPE_LABELS[pp.type]}
                                        </span>
                                    </div>
                                </button>
                            ))}
                    </div>
                </div>
            )}
        </div>
    );
}
```

**Step 2: Wire into plot page**

Replace the list placeholder in `pages/plot/index.tsx`:

```tsx
import PlotPointList from '@/components/plot/PlotPointList';

// In the list tab:
<PlotPointList
    acts={acts}
    plotPoints={filteredPlotPoints}
    storylines={storylines}
    onSelectPlotPoint={setSelectedPlotPoint}
/>;
```

**Step 3: Build**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/js/components/plot/PlotPointList.tsx resources/js/pages/plot/index.tsx
git commit -m "feat(plot): add PlotPointList component"
```

---

## Phase 7: AI Action Sidebar

### Task 22: PlotAiController (Backend)

**Files:**

- Create: `app/Http/Controllers/PlotAiController.php`

**Step 1: Create controller**

Run: `php artisan make:controller PlotAiController --no-interaction`

**Step 2: Write controller with 4 AI actions**

Each action dispatches an existing or new analysis job, returning a JSON status. Results are stored as `Analysis` records.

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisType;
use App\Jobs\RunAnalysisJob;
use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class PlotAiController extends Controller
{
    public function runPlotHealth(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();
        RunAnalysisJob::dispatch($book, AnalysisType::ThrillerHealth);

        return response()->json(['message' => 'Plot health analysis started.']);
    }

    public function detectPlotHoles(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();
        RunAnalysisJob::dispatch($book, AnalysisType::Plothole);

        return response()->json(['message' => 'Plot hole detection started.']);
    }

    public function suggestBeats(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();
        RunAnalysisJob::dispatch($book, AnalysisType::NextChapterSuggestion);

        return response()->json(['message' => 'Beat suggestion started.']);
    }

    public function generateTensionArc(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        // Re-use chapter tension scores from existing chapter analysis
        // If chapters don't have tension scores yet, trigger full analysis
        $chapters = $book->chapters()->whereNotNull('tension_score')->get();

        if ($chapters->isEmpty()) {
            return response()->json([
                'message' => 'No tension data available. Run chapter analysis first via AI Preparation.',
            ], 422);
        }

        $tensionData = $chapters->map(fn ($ch) => [
            'chapter_id' => $ch->id,
            'title' => $ch->title,
            'reader_order' => $ch->reader_order,
            'tension_score' => $ch->tension_score,
        ])->sortBy('reader_order')->values();

        return response()->json([
            'tension_arc' => $tensionData,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function analysisStatus(Book $book): JsonResponse
    {
        $analyses = $book->analyses()
            ->whereNull('chapter_id')
            ->get()
            ->keyBy(fn ($a) => $a->type->value);

        return response()->json(['analyses' => $analyses]);
    }

    private function ensureAiConfigured(): void
    {
        set_time_limit(300);
        $setting = AiSetting::activeProvider();
        abort_if(! $setting || ! $setting->isConfigured(), 422, 'No AI provider configured.');
        $setting->injectConfig();
    }
}
```

**Step 3: Add routes** (inside the `license` middleware group in `routes/web.php`)

```php
Route::post('/books/{book}/plot/ai/health', [PlotAiController::class, 'runPlotHealth'])->name('books.plot.ai.health');
Route::post('/books/{book}/plot/ai/holes', [PlotAiController::class, 'detectPlotHoles'])->name('books.plot.ai.holes');
Route::post('/books/{book}/plot/ai/beats', [PlotAiController::class, 'suggestBeats'])->name('books.plot.ai.beats');
Route::post('/books/{book}/plot/ai/tension', [PlotAiController::class, 'generateTensionArc'])->name('books.plot.ai.tension');
Route::get('/books/{book}/plot/ai/status', [PlotAiController::class, 'analysisStatus'])->name('books.plot.ai.status');
```

Add the use statement:

```php
use App\Http\Controllers\PlotAiController;
```

**Step 4: Run Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`

**Step 5: Commit**

```bash
git add app/Http/Controllers/PlotAiController.php routes/web.php
git commit -m "feat(plot): add PlotAiController with 4 AI actions"
```

### Task 23: AiActionSidebar Component

**Files:**

- Create: `resources/js/components/plot/AiActionSidebar.tsx`

**Step 1: Build the sidebar**

Follow Paper design "8a — Plot (AI Sidebar Open)". Collapsible (40px collapsed, 280px expanded). Contains 4 action buttons with results display.

```tsx
import { useAiFeatures } from '@/hooks/useAiFeatures';
import { router } from '@inertiajs/react';
import {
    Lightning,
    ArrowsClockwise,
    MagnifyingGlass,
    Sparkle,
} from '@phosphor-icons/react';
import { useState } from 'react';

type AnalysisResult = {
    score?: number;
    findings?: { severity: string; description: string }[];
    recommendations?: string[];
};

type Props = {
    bookId: number;
    collapsed: boolean;
    onToggle: () => void;
};

export default function AiActionSidebar({
    bookId,
    collapsed,
    onToggle,
}: Props) {
    const ai = useAiFeatures();
    const [loading, setLoading] = useState<string | null>(null);
    const [healthResult, setHealthResult] = useState<AnalysisResult | null>(
        null,
    );
    const [holesResult, setHolesResult] = useState<AnalysisResult | null>(null);
    const [beatsResult, setBeatsResult] = useState<string[] | null>(null);
    const [tensionArc, setTensionArc] = useState<
        | { chapter_id: number; tension_score: number; reader_order: number }[]
        | null
    >(null);

    if (!ai.visible) return null;

    const runAction = async (action: string) => {
        setLoading(action);
        try {
            const response = await fetch(`/books/${bookId}/plot/ai/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
            });
            const data = await response.json();

            if (action === 'tension') {
                setTensionArc(data.tension_arc);
            }
            // Poll for async results
            if (action !== 'tension') {
                pollResults();
            }
        } finally {
            setLoading(null);
        }
    };

    const pollResults = async () => {
        const response = await fetch(`/books/${bookId}/plot/ai/status`);
        const data = await response.json();
        const analyses = data.analyses;

        if (analyses.thriller_health?.result)
            setHealthResult(analyses.thriller_health.result as AnalysisResult);
        if (analyses.plothole?.result)
            setHolesResult(analyses.plothole.result as AnalysisResult);
        if (analyses.next_chapter_suggestion?.result) {
            const result = analyses.next_chapter_suggestion.result as {
                recommendations?: string[];
            };
            setBeatsResult(result.recommendations ?? []);
        }
    };

    if (collapsed) {
        return (
            <button
                onClick={onToggle}
                className="flex h-full w-[40px] flex-shrink-0 flex-col items-center border-l border-[#ECEAE4] bg-white pt-3"
            >
                <Lightning size={18} className="text-[#8A857D]" />
            </button>
        );
    }

    return (
        <div className="flex h-full w-[280px] flex-shrink-0 flex-col border-l border-[#ECEAE4] bg-white">
            <div className="flex items-center justify-between border-b border-[#ECEAE4] px-4 py-3">
                <span className="text-xs font-semibold tracking-wide text-[#8A857D] uppercase">
                    AI Actions
                </span>
                <button
                    onClick={onToggle}
                    className="text-[#8A857D] hover:text-[#5A574F]"
                >
                    <ArrowsClockwise size={14} />
                </button>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-3">
                {!ai.usable && (
                    <p className="mb-4 text-xs text-[#B0A99F]">
                        AI features require a PRO license and configured
                        provider.
                    </p>
                )}

                {/* Generate Tension Arc */}
                <ActionButton
                    icon={<Sparkle size={14} />}
                    label="Generate Tension Arc"
                    description="Visualize tension across chapters"
                    onClick={() => runAction('tension')}
                    loading={loading === 'tension'}
                    disabled={!ai.usable}
                />

                {/* Plot Health */}
                <ActionButton
                    icon={<Lightning size={14} />}
                    label="Run Plot Health"
                    description="Evaluate overall structure"
                    onClick={() => runAction('health')}
                    loading={loading === 'health'}
                    disabled={!ai.usable}
                />
                {healthResult && (
                    <div className="mb-3 rounded bg-[#FAFAF7] p-2.5 text-xs text-[#5A574F]">
                        <div className="mb-1 font-semibold">
                            Score: {healthResult.score}/10
                        </div>
                        {healthResult.findings?.slice(0, 3).map((f, i) => (
                            <p key={i} className="mt-1 text-[#8A857D]">
                                • {f.description}
                            </p>
                        ))}
                    </div>
                )}

                {/* Detect Plot Holes */}
                <ActionButton
                    icon={<MagnifyingGlass size={14} />}
                    label="Detect Plot Holes"
                    description="Find gaps and contradictions"
                    onClick={() => runAction('holes')}
                    loading={loading === 'holes'}
                    disabled={!ai.usable}
                />
                {holesResult && (
                    <div className="mb-3 rounded bg-[#FAFAF7] p-2.5 text-xs text-[#5A574F]">
                        {holesResult.findings?.map((f, i) => (
                            <p key={i} className="mt-1">
                                <span
                                    className={
                                        f.severity === 'high'
                                            ? 'text-[#8E5A5A]'
                                            : 'text-[#8A857D]'
                                    }
                                >
                                    [{f.severity}]
                                </span>{' '}
                                {f.description}
                            </p>
                        ))}
                    </div>
                )}

                {/* Suggest Beats */}
                <ActionButton
                    icon={<Sparkle size={14} />}
                    label="Suggest Next Beats"
                    description="AI-recommended plot points"
                    onClick={() => runAction('beats')}
                    loading={loading === 'beats'}
                    disabled={!ai.usable}
                />
                {beatsResult && (
                    <div className="mb-3 rounded bg-[#FAFAF7] p-2.5 text-xs text-[#5A574F]">
                        {beatsResult.map((b, i) => (
                            <p key={i} className="mt-1">
                                {i + 1}. {b}
                            </p>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function ActionButton({
    icon,
    label,
    description,
    onClick,
    loading,
    disabled,
}: {
    icon: React.ReactNode;
    label: string;
    description: string;
    onClick: () => void;
    loading: boolean;
    disabled: boolean;
}) {
    return (
        <button
            onClick={onClick}
            disabled={disabled || loading}
            className="mb-3 flex w-full items-start gap-2.5 rounded-lg border border-[#ECEAE4] px-3 py-2.5 text-left hover:bg-[#FAFAF7] disabled:opacity-50"
        >
            <span className="mt-0.5 text-[#8A857D]">{icon}</span>
            <div>
                <span className="text-xs font-medium text-[#2D2A26]">
                    {loading ? 'Running...' : label}
                </span>
                <p className="text-[10px] text-[#B0A99F]">{description}</p>
            </div>
        </button>
    );
}
```

**Step 2: Wire into plot page**

In `resources/js/pages/plot/index.tsx`:

- Add state: `const [aiSidebarOpen, setAiSidebarOpen] = useState(false);`
- Render `AiActionSidebar` after the main content area
- Update the AI toggle button in the header to call `setAiSidebarOpen`

**Step 3: Build**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/js/components/plot/AiActionSidebar.tsx resources/js/pages/plot/index.tsx
git commit -m "feat(plot): add AiActionSidebar component"
```

### Task 24: Test PlotAiController

**Files:**

- Create: `tests/Feature/PlotAiControllerTest.php`

**Step 1: Create test**

Run: `php artisan make:test PlotAiControllerTest --pest --no-interaction`

**Step 2: Write tests**

```php
<?php

use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

it('returns tension arc from existing chapter scores', function () {
    License::factory()->create(['is_active' => true]);
    AiSetting::factory()->create(['enabled' => true]);
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    Chapter::factory()->create([
        'book_id' => $book->id,
        'storyline_id' => $storyline->id,
        'tension_score' => 7,
        'reader_order' => 0,
    ]);

    $this->postJson(route('books.plot.ai.tension', $book))
        ->assertOk()
        ->assertJsonStructure(['tension_arc', 'generated_at']);
});

it('returns 422 when no tension data available', function () {
    License::factory()->create(['is_active' => true]);
    AiSetting::factory()->create(['enabled' => true]);
    $book = Book::factory()->create();

    $this->postJson(route('books.plot.ai.tension', $book))
        ->assertUnprocessable();
});

it('returns analysis status', function () {
    License::factory()->create(['is_active' => true]);
    $book = Book::factory()->create();

    $this->getJson(route('books.plot.ai.status', $book))
        ->assertOk()
        ->assertJsonStructure(['analyses']);
});
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=PlotAiControllerTest`
Expected: Tests PASS (adjust if License factory pattern differs — check existing tests for the correct approach)

**Step 4: Commit**

```bash
git add tests/Feature/PlotAiControllerTest.php
git commit -m "test(plot): add PlotAiController tests"
```

---

## Phase 8: Tension Arc Strip

### Task 25: TensionArc Component

**Files:**

- Create: `resources/js/components/plot/TensionArc.tsx`

**Step 1: Build the SVG tension curve**

Follow Paper design "8c — Plot (Tension Arc)". Gold line `#C8B88A` with gradient fill, positioned above the timeline grid.

```tsx
type TensionData = {
    chapter_id: number;
    reader_order: number;
    tension_score: number;
    title?: string;
};

type Props = {
    data: TensionData[];
    chapterCount: number;
    labelWidth: number;
    columnWidth: number;
    onCollapse: () => void;
};

export default function TensionArc({
    data,
    chapterCount,
    labelWidth,
    columnWidth,
    onCollapse,
}: Props) {
    if (data.length === 0) return null;

    const W = chapterCount * columnWidth;
    const H = 60;
    const sorted = [...data].sort((a, b) => a.reader_order - b.reader_order);

    const points = sorted.map((d, i) => {
        const x = (i + 0.5) * columnWidth;
        const y = H - (d.tension_score / 100) * (H - 12) - 6;
        return { x, y, score: d.tension_score };
    });

    // Build smooth curve path
    const pathD = points.reduce((acc, p, i) => {
        if (i === 0) return `M ${p.x} ${p.y}`;
        const prev = points[i - 1];
        const cpx = (prev.x + p.x) / 2;
        return `${acc} C ${cpx} ${prev.y}, ${cpx} ${p.y}, ${p.x} ${p.y}`;
    }, '');

    const areaD = `${pathD} L ${points[points.length - 1].x} ${H} L ${points[0].x} ${H} Z`;

    return (
        <div className="flex border-b border-[#ECEAE4]">
            <div
                className="flex flex-col justify-center px-3"
                style={{ width: labelWidth, flexShrink: 0 }}
            >
                <span className="text-[10px] font-semibold tracking-wide text-[#8A857D] uppercase">
                    Tension
                </span>
                <span className="text-[9px] text-[#B0A99F]">AI generated</span>
            </div>
            <div className="relative" style={{ width: W }}>
                <svg width={W} height={H} className="block">
                    <defs>
                        <linearGradient
                            id="tensionGrad"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="#C8B88A"
                                stopOpacity="0.2"
                            />
                            <stop
                                offset="100%"
                                stopColor="#C8B88A"
                                stopOpacity="0"
                            />
                        </linearGradient>
                    </defs>
                    <path d={areaD} fill="url(#tensionGrad)" />
                    <path
                        d={pathD}
                        fill="none"
                        stroke="#C8B88A"
                        strokeWidth="2"
                    />
                    {points.map((p, i) => (
                        <g key={i}>
                            <circle cx={p.x} cy={p.y} r="3" fill="#C8B88A" />
                            <text
                                x={p.x}
                                y={H - 2}
                                textAnchor="middle"
                                className="fill-[#B0A99F] text-[9px]"
                            >
                                {p.score}
                            </text>
                        </g>
                    ))}
                </svg>
            </div>
        </div>
    );
}
```

**Step 2: Wire into plot page**

In `resources/js/pages/plot/index.tsx`, render `TensionArc` above the `SwimLaneTimeline` when tension data is available (from AI sidebar action or from existing chapter tension scores).

Add state:

```tsx
const [tensionData, setTensionData] = useState<
    { chapter_id: number; reader_order: number; tension_score: number }[] | null
>(null);
const [tensionArcVisible, setTensionArcVisible] = useState(false);
```

Pass `setTensionData` to the AI sidebar so it can set tension data when "Generate Tension Arc" runs.

**Step 3: Build**

Run: `npm run build`

**Step 4: Commit**

```bash
git add resources/js/components/plot/TensionArc.tsx resources/js/pages/plot/index.tsx
git commit -m "feat(plot): add TensionArc SVG component"
```

---

## Phase 9: Editor Sidebar Beats

### Task 26: ChapterBeats Component

**Files:**

- Create: `resources/js/components/editor/ChapterBeats.tsx`

**Step 1: Build the beats panel**

A compact list of plot points for the active chapter, shown in the Editor sidebar.

```tsx
import type { PlotPoint } from '@/types/models';
import { router } from '@inertiajs/react';

const TYPE_STYLES: Record<string, string> = {
    setup: 'bg-[#EDE8F5] text-[#6B5A8E]',
    conflict: 'bg-[#F5E8E8] text-[#8E5A5A]',
    turning_point: 'bg-[#F5EDE0] text-[#8A7A5A]',
    resolution: 'bg-[#E8F0E8] text-[#5A8E5A]',
    worldbuilding: 'bg-[#E8EDF5] text-[#5A6B8E]',
};

const TYPE_LABELS: Record<string, string> = {
    setup: 'Setup',
    conflict: 'Conflict',
    turning_point: 'Turning pt.',
    resolution: 'Resolution',
    worldbuilding: 'World',
};

const STATUS_COLORS: Record<string, string> = {
    planned: '#D4A843',
    fulfilled: '#6DBB7B',
    abandoned: '#B0A99F',
};

const NEXT_STATUS: Record<string, string> = {
    planned: 'fulfilled',
    fulfilled: 'abandoned',
    abandoned: 'planned',
};

type Props = {
    plotPoints: PlotPoint[];
    bookId: number;
    chapterId: number;
};

export default function ChapterBeats({ plotPoints, bookId, chapterId }: Props) {
    const cycleStatus = (pp: PlotPoint) => {
        router.patch(
            route('plotPoints.updateStatus', [bookId, pp.id]),
            { status: NEXT_STATUS[pp.status] },
            { preserveScroll: true },
        );
    };

    return (
        <div className="px-3 py-2">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-[10px] font-semibold tracking-wider text-[#8A857D] uppercase">
                    Beats
                </span>
                <button
                    onClick={() => {
                        router.post(
                            route('plotPoints.store', bookId),
                            {
                                title: 'New beat',
                                type: 'setup',
                                intended_chapter_id: chapterId,
                            },
                            { preserveScroll: true },
                        );
                    }}
                    className="text-[10px] text-[#8A857D] hover:text-[#5A574F]"
                >
                    + Add
                </button>
            </div>

            {plotPoints.length === 0 && (
                <p className="text-[11px] text-[#B0A99F]">
                    No beats for this chapter.
                </p>
            )}

            {plotPoints.map((pp) => (
                <div key={pp.id} className="mb-1.5 flex items-center gap-2">
                    <button
                        onClick={() => cycleStatus(pp)}
                        className="h-2.5 w-2.5 flex-shrink-0 rounded-full border border-transparent transition-colors"
                        style={{ backgroundColor: STATUS_COLORS[pp.status] }}
                        title={`Status: ${pp.status} — click to cycle`}
                    />
                    <span className="flex-1 truncate text-[11px] text-[#2D2A26]">
                        {pp.title}
                    </span>
                    <span
                        className={`rounded px-1 py-0.5 text-[8px] font-medium ${TYPE_STYLES[pp.type]}`}
                    >
                        {TYPE_LABELS[pp.type]}
                    </span>
                </div>
            ))}
        </div>
    );
}
```

**Step 2: Integrate into Editor Sidebar**

In `resources/js/components/editor/Sidebar.tsx`, add a `ChapterBeats` section when a chapter is active. This requires the chapter's plot points to be passed from the Editor page.

The Editor page (`resources/js/pages/chapters/show.tsx` or similar) needs to pass `chapterPlotPoints` as a prop. Add to the `ChapterController::show` method:

```php
'chapterPlotPoints' => $chapter->book->plotPoints()
    ->where('intended_chapter_id', $chapter->id)
    ->orderBy('sort_order')
    ->get(),
```

**Step 3: Commit**

```bash
git add resources/js/components/editor/ChapterBeats.tsx
git commit -m "feat(plot): add ChapterBeats component for editor sidebar"
```

### Task 27: Wire ChapterBeats into Editor

**Files:**

- Modify: `app/Http/Controllers/ChapterController.php` (add chapterPlotPoints to show response)
- Modify: `resources/js/components/editor/Sidebar.tsx` (render ChapterBeats)

**Step 1: Add plot points to chapter show**

In `ChapterController::show`, add to the Inertia response:

```php
'chapterPlotPoints' => $chapter->book->plotPoints()
    ->where('intended_chapter_id', $chapter->id)
    ->orderBy('sort_order')
    ->get(),
```

**Step 2: Render ChapterBeats in Sidebar**

Import `ChapterBeats` and render it in the sidebar below the chapter list, when a chapter is active. The sidebar receives `plotPoints` via page props.

**Step 3: Build**

Run: `npm run build`

**Step 4: Commit**

```bash
git add app/Http/Controllers/ChapterController.php resources/js/components/editor/Sidebar.tsx
git commit -m "feat(plot): integrate ChapterBeats into editor sidebar"
```

---

## Phase 10: Final Polish + Run All Tests

### Task 28: Run Pint + Full Test Suite

**Step 1: Run Pint on all modified PHP files**

Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Run all plot-related tests**

Run: `php artisan test --compact --filter=PlotPoint`

Expected: All tests PASS

**Step 3: Run full build**

Run: `npm run build`

**Step 4: Commit any Pint fixes**

```bash
git add -A
git commit -m "chore(plot): lint fixes"
```

### Task 29: Run Wayfinder + Final Verification

**Step 1: Regenerate Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`

**Step 2: Run full test suite**

Run: `php artisan test --compact`

**Step 3: Build frontend**

Run: `npm run build`

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat(plot): complete Plot feature reimagining"
```

---

## Summary

| Phase                   | Tasks | What it builds                                                                   |
| ----------------------- | ----- | -------------------------------------------------------------------------------- |
| 1 — Data Model          | 1–6   | ConnectionType enum, migration, PlotPointConnection model, relationships         |
| 2 — Backend API         | 7–13  | PlotPointController, PlotPointConnectionController, form requests, routes, tests |
| 3 — Frontend Foundation | 14–15 | TypeScript types, Plot page layout with tabs                                     |
| 4 — Swimlane Timeline   | 16–19 | Grid utility, SwimLaneTimeline, PlotPointCard, quick create                      |
| 5 — Detail Panel        | 20    | DetailPanel with edit, connections, jump-to-chapter                              |
| 6 — List View           | 21    | PlotPointList grouped by acts                                                    |
| 7 — AI Sidebar          | 22–24 | PlotAiController, AiActionSidebar, tests                                         |
| 8 — Tension Arc         | 25    | TensionArc SVG component                                                         |
| 9 — Editor Beats        | 26–27 | ChapterBeats in editor sidebar                                                   |
| 10 — Polish             | 28–29 | Lint, tests, build verification                                                  |

**Deferred to follow-up iterations:**

- Drag-and-drop reordering (dnd-kit integration for card movement between cells)
- Connection drag-to-create (SVG overlay with drag handles)
- Connection SVG arrows on timeline (rendering curves between cards)
- Tension arc data persistence in local storage
- AI polling with auto-refresh (like useChapterAnalysis pattern)

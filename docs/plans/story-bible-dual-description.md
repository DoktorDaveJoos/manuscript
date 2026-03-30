# Story Bible Dual-Description Model

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the single `description` field on characters and wiki entries into two fields — `description` (author's manual notes, AI never touches) and `ai_description` (AI-derived from manuscript) — so AI Prep can enrich the story bible without overwriting the author's work.

**Architecture:** Add `ai_description` column to both `characters` and `wiki_entries` tables. Migrate existing data based on `is_ai_extracted` flag. Update `PersistsExtractedEntities` to write only to `ai_description` and never flip `is_ai_extracted` on manual entries. Add fuzzy name matching (normalized + alias-aware) for entity lookup. Update consolidation to see all entries but only merge AI into manual. Update frontend to show both fields — editable author notes + read-only AI block.

**Tech Stack:** Laravel 12 (PHP 8.4), Pest 4, Inertia v2 + React 19, Tailwind v4

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/xxxx_add_ai_description_to_characters_and_wiki_entries.php` | Add `ai_description` column, migrate data |
| Modify | `app/Models/Character.php` | Add `ai_description` to casts, add `fullDescription()` accessor |
| Modify | `app/Models/WikiEntry.php` | Add `ai_description` to casts, add `fullDescription()` accessor |
| Create | `app/Support/EntityNameMatcher.php` | Fuzzy name matching (normalize + alias lookup) |
| Modify | `app/Jobs/Concerns/PersistsExtractedEntities.php` | Write to `ai_description`, preserve `is_ai_extracted`, use fuzzy matching |
| Modify | `app/Jobs/Preparation/ConsolidateEntities.php` | See all entries, merge AI into manual (manual stays canonical) |
| Modify | `app/Ai/Tools/LookupExistingEntities.php` | Include both description fields in output |
| Modify | `app/Ai/Tools/RetrieveManuscriptContext.php` | Concatenate both description fields |
| Modify | `app/Ai/Agents/ProseReviser.php:116-117,139-140` | Concatenate both description fields |
| Modify | `app/Http/Controllers/WikiController.php` | Ensure manual edits write to `description`, never `ai_description` |
| Modify | `resources/js/types/models.ts` | Add `ai_description` to Character and WikiEntry types |
| Modify | `resources/js/components/wiki/CharacterDetail.tsx` | Show both description sections |
| Modify | `resources/js/components/wiki/WikiEntryDetail.tsx` | Show both description sections |
| Modify | `resources/js/components/wiki/WikiForm.tsx` | Only edit `description`, show read-only `ai_description` |
| Modify | `resources/js/i18n/en/wiki.json` | Add translation keys for AI description section |
| Create | `tests/Feature/Support/EntityNameMatcherTest.php` | Test fuzzy matching |
| Modify | `tests/Feature/Ai/EntityExtractorTest.php` | Update for dual-description behavior |
| Modify | `tests/Feature/Jobs/ConsolidateEntitiesTest.php` | Add manual-entry protection tests |

---

### Task 1: Migration — Add `ai_description` Column

**Files:**
- Create: `database/migrations/xxxx_add_ai_description_to_characters_and_wiki_entries.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_ai_description_to_characters_and_wiki_entries --no-interaction
```

- [ ] **Step 2: Write the migration**

Open the newly created migration file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->text('ai_description')->nullable()->after('description');
        });

        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->text('ai_description')->nullable()->after('description');
        });

        // Move AI-extracted descriptions to ai_description column
        DB::table('characters')
            ->where('is_ai_extracted', true)
            ->whereNotNull('description')
            ->update([
                'ai_description' => DB::raw('description'),
                'description' => null,
            ]);

        DB::table('wiki_entries')
            ->where('is_ai_extracted', true)
            ->whereNotNull('description')
            ->update([
                'ai_description' => DB::raw('description'),
                'description' => null,
            ]);
    }

    public function down(): void
    {
        // Move ai_description back to description where description is null
        DB::table('characters')
            ->whereNull('description')
            ->whereNotNull('ai_description')
            ->update([
                'description' => DB::raw('ai_description'),
            ]);

        DB::table('wiki_entries')
            ->whereNull('description')
            ->whereNotNull('ai_description')
            ->update([
                'description' => DB::raw('ai_description'),
            ]);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('ai_description');
        });

        Schema::table('wiki_entries', function (Blueprint $table) {
            $table->dropColumn('ai_description');
        });
    }
};
```

- [ ] **Step 3: Run the migration against both databases**

```bash
php artisan migrate --no-interaction
DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*add_ai_description*
git commit -m "feat: add ai_description column to characters and wiki_entries tables"
```

---

### Task 2: EntityNameMatcher — Fuzzy Name Matching

**Files:**
- Create: `app/Support/EntityNameMatcher.php`
- Create: `tests/Feature/Support/EntityNameMatcherTest.php`

- [ ] **Step 1: Write the failing test**

```bash
php artisan make:test Support/EntityNameMatcherTest --pest --no-interaction
```

Replace the test file contents with:

```php
<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;
use App\Support\EntityNameMatcher;

test('matches character by exact name', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());
    $match = $matcher->findCharacter('Maja Paulsen');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($character->id);
});

test('matches character case-insensitively', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());
    $match = $matcher->findCharacter('maja paulsen');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($character->id);
});

test('matches character after stripping leading articles', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create(['name' => 'The Dark Knight']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Dark Knight'))->not->toBeNull()
        ->and($matcher->findCharacter('the dark knight'))->not->toBeNull();
});

test('matches character by alias', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create([
        'name' => 'Maja Paulsen',
        'aliases' => ['Maja', 'The Commander'],
    ]);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Maja'))->not->toBeNull()
        ->and($matcher->findCharacter('The Commander'))->not->toBeNull()
        ->and($matcher->findCharacter('commander'))->not->toBeNull();
});

test('returns null for no match', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('Unknown Person'))->toBeNull();
});

test('matches wiki entry by exact name and kind', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);
    $match = $matcher->findWikiEntry('The Brass Lantern', 'location');

    expect($match)->not->toBeNull()
        ->and($match->id)->toBe($entry->id);
});

test('matches wiki entry case-insensitively with article stripping', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('brass lantern', 'location'))->not->toBeNull()
        ->and($matcher->findWikiEntry('Brass Lantern', 'location'))->not->toBeNull();
});

test('matches wiki entry by alias', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->organization()->for($book)->create([
        'name' => 'Green Zone Protection Party',
        'metadata' => ['aliases' => ['GZP', 'The Party']],
    ]);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('GZP', 'organization'))->not->toBeNull()
        ->and($matcher->findWikiEntry('the party', 'organization'))->not->toBeNull();
});

test('does not match wiki entry with wrong kind', function () {
    $book = Book::factory()->create();
    WikiEntry::factory()->location()->for($book)->create(['name' => 'The Brass Lantern']);
    $entries = $book->wikiEntries()->get();

    $matcher = new EntityNameMatcher(collect(), $entries);

    expect($matcher->findWikiEntry('The Brass Lantern', 'organization'))->toBeNull();
});

test('handles whitespace trimming', function () {
    $book = Book::factory()->create();
    Character::factory()->for($book)->create(['name' => 'Maja Paulsen']);
    $characters = $book->characters()->get();

    $matcher = new EntityNameMatcher($characters, collect());

    expect($matcher->findCharacter('  Maja Paulsen  '))->not->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact --filter=EntityNameMatcherTest
```

Expected: FAIL — `EntityNameMatcher` class does not exist.

- [ ] **Step 3: Create the EntityNameMatcher class**

```bash
php artisan make:class Support/EntityNameMatcher --no-interaction
```

Replace contents with:

```php
<?php

namespace App\Support;

use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Support\Collection;

class EntityNameMatcher
{
    /** @var array<string, Character> */
    private array $characterIndex = [];

    /** @var array<string, array<string, WikiEntry>> */
    private array $wikiEntryIndex = [];

    private const ARTICLES = ['the', 'a', 'an', 'der', 'die', 'das', 'ein', 'eine', 'le', 'la', 'les', 'el', 'los', 'las'];

    /**
     * @param  Collection<int, Character>  $characters
     * @param  Collection<int, WikiEntry>  $wikiEntries
     */
    public function __construct(Collection $characters, Collection $wikiEntries)
    {
        $this->buildCharacterIndex($characters);
        $this->buildWikiEntryIndex($wikiEntries);
    }

    public function findCharacter(string $name): ?Character
    {
        $normalized = self::normalize($name);

        return $this->characterIndex[$normalized] ?? null;
    }

    public function findWikiEntry(string $name, string $kind): ?WikiEntry
    {
        $normalized = self::normalize($name);

        return $this->wikiEntryIndex[$kind][$normalized] ?? null;
    }

    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Strip leading articles
        foreach (self::ARTICLES as $article) {
            $prefix = $article.' ';
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return trim($name);
    }

    /**
     * @param  Collection<int, Character>  $characters
     */
    private function buildCharacterIndex(Collection $characters): void
    {
        foreach ($characters as $character) {
            // Index by normalized name
            $this->characterIndex[self::normalize($character->name)] = $character;

            // Index by normalized aliases
            foreach ($character->aliases ?? [] as $alias) {
                $normalizedAlias = self::normalize($alias);
                // Don't overwrite if a primary name already occupies this key
                $this->characterIndex[$normalizedAlias] ??= $character;
            }
        }
    }

    /**
     * @param  Collection<int, WikiEntry>  $wikiEntries
     */
    private function buildWikiEntryIndex(Collection $wikiEntries): void
    {
        foreach ($wikiEntries as $entry) {
            $kind = $entry->kind->value;

            // Index by normalized name within kind
            $this->wikiEntryIndex[$kind][self::normalize($entry->name)] = $entry;

            // Index by normalized aliases within kind
            foreach ($entry->metadata['aliases'] ?? [] as $alias) {
                $normalizedAlias = self::normalize($alias);
                $this->wikiEntryIndex[$kind][$normalizedAlias] ??= $entry;
            }
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --compact --filter=EntityNameMatcherTest
```

Expected: All 10 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/EntityNameMatcher.php tests/Feature/Support/EntityNameMatcherTest.php
git commit -m "feat: add EntityNameMatcher with normalized and alias-aware fuzzy matching"
```

---

### Task 3: Update Models — Add `ai_description`

**Files:**
- Modify: `app/Models/Character.php`
- Modify: `app/Models/WikiEntry.php`

- [ ] **Step 1: Update Character model**

In `app/Models/Character.php`, no changes to `$guarded` needed (it's `[]`). Add a `fullDescription()` method that concatenates both fields for AI agent context:

```php
/**
 * Get the combined description (manual + AI) for use in AI agent context.
 */
public function fullDescription(): ?string
{
    $parts = array_filter([
        $this->description,
        $this->ai_description,
    ]);

    return $parts ? implode("\n\n", $parts) : null;
}
```

- [ ] **Step 2: Update WikiEntry model**

In `app/Models/WikiEntry.php`, add the same `fullDescription()` method:

```php
/**
 * Get the combined description (manual + AI) for use in AI agent context.
 */
public function fullDescription(): ?string
{
    $parts = array_filter([
        $this->description,
        $this->ai_description,
    ]);

    return $parts ? implode("\n\n", $parts) : null;
}
```

- [ ] **Step 3: Run existing tests to verify no regression**

```bash
php artisan test --compact --filter=WikiEntryTest
php artisan test --compact --filter=WikiEntryCreationTest
```

Expected: All PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Character.php app/Models/WikiEntry.php
git commit -m "feat: add ai_description field and fullDescription() to Character and WikiEntry models"
```

---

### Task 4: Update PersistsExtractedEntities — Protect Manual Entries

**Files:**
- Modify: `app/Jobs/Concerns/PersistsExtractedEntities.php`
- Modify: `tests/Feature/Ai/EntityExtractorTest.php`

- [ ] **Step 1: Write failing tests for manual entry protection**

Add these tests to `tests/Feature/Ai/EntityExtractorTest.php`:

```php
test('extract entities job does not overwrite manual character description', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->characters()->create([
        'name' => 'Hans Mueller',
        'aliases' => [],
        'description' => 'My custom notes about Hans.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => ['Hans'],
                    'description' => 'A brave hero who fights for justice in every chapter.',
                    'role' => 'protagonist',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller arrived.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans->description)->toBe('My custom notes about Hans.')
        ->and($hans->ai_description)->toBe('A brave hero who fights for justice in every chapter.')
        ->and($hans->is_ai_extracted)->toBeFalse()
        ->and($hans->aliases)->toContain('Hans');
});

test('extract entities job does not overwrite manual wiki entry description', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->wikiEntries()->create([
        'name' => 'The Brass Lantern',
        'kind' => 'location',
        'description' => 'My notes about the tavern.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [],
            'entities' => [
                [
                    'name' => 'The Brass Lantern',
                    'kind' => 'location',
                    'type' => 'Tavern',
                    'description' => 'A recurring meeting place mentioned throughout the story.',
                ],
            ],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'They met at The Brass Lantern.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $entry = $book->wikiEntries()->where('name', 'The Brass Lantern')->first();
    expect($entry->description)->toBe('My notes about the tavern.')
        ->and($entry->ai_description)->toBe('A recurring meeting place mentioned throughout the story.')
        ->and($entry->is_ai_extracted)->toBeFalse();
});

test('extract entities job writes ai_description for new ai-extracted entries', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'New Character',
                    'aliases' => [],
                    'description' => 'Found in the text.',
                    'role' => 'mentioned',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'New Character appeared.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $character = $book->characters()->where('name', 'New Character')->first();
    expect($character->description)->toBeNull()
        ->and($character->ai_description)->toBe('Found in the text.')
        ->and($character->is_ai_extracted)->toBeTrue();
});

test('extract entities job matches manual entry with fuzzy name', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->wikiEntries()->create([
        'name' => 'The Crimson Blade',
        'kind' => 'item',
        'description' => 'A legendary sword.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [],
            'entities' => [
                [
                    'name' => 'Crimson Blade',
                    'kind' => 'item',
                    'type' => 'Weapon',
                    'description' => 'A sword used in the battle scene.',
                ],
            ],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'He drew the Crimson Blade.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    // Should match existing entry, not create a duplicate
    expect($book->wikiEntries()->count())->toBe(1);

    $entry = $book->wikiEntries()->first();
    expect($entry->name)->toBe('The Crimson Blade')
        ->and($entry->description)->toBe('A legendary sword.')
        ->and($entry->ai_description)->toBe('A sword used in the battle scene.')
        ->and($entry->is_ai_extracted)->toBeFalse();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
php artisan test --compact --filter="does not overwrite manual character"
php artisan test --compact --filter="does not overwrite manual wiki entry"
php artisan test --compact --filter="writes ai_description for new"
php artisan test --compact --filter="matches manual entry with fuzzy"
```

Expected: All FAIL.

- [ ] **Step 3: Rewrite PersistsExtractedEntities**

Replace the full contents of `app/Jobs/Concerns/PersistsExtractedEntities.php`:

```php
<?php

namespace App\Jobs\Concerns;

use App\Models\Book;
use App\Models\Chapter;
use App\Support\EntityNameMatcher;
use Illuminate\Database\Eloquent\Model;

trait PersistsExtractedEntities
{
    /**
     * Persist extracted characters and wiki entries from an EntityExtractor response.
     *
     * @param  array<string, mixed>  $response
     */
    protected function persistExtractedEntities(Book $book, Chapter $chapter, array $response): void
    {
        $characters = $book->characters()->get();
        $wikiEntries = $book->wikiEntries()->get();
        $matcher = new EntityNameMatcher($characters, $wikiEntries);

        $this->persistCharacters($book, $chapter, $response['characters'] ?? [], $matcher);
        $this->persistWikiEntries($book, $chapter, $response['entities'] ?? [], $matcher);
    }

    /**
     * @param  array<int, array<string, mixed>>  $characters
     */
    private function persistCharacters(Book $book, Chapter $chapter, array $characters, EntityNameMatcher $matcher): void
    {
        if (empty($characters)) {
            return;
        }

        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($characters as $characterData) {
            if (! is_array($characterData) || empty($characterData['name'])) {
                continue;
            }

            $name = $characterData['name'];
            $character = $matcher->findCharacter($name);
            $isNew = ! $character;

            if ($isNew) {
                $character = $book->characters()->make(['name' => $name]);
            }

            // Merge aliases (additive, safe for both manual and AI entries)
            $character->aliases = array_values(array_unique(array_merge(
                $character->aliases ?? [],
                $characterData['aliases'] ?? [],
            )));

            // AI always writes to ai_description, never to description
            $newDescription = $characterData['description'] ?? null;
            if (! $character->ai_description || mb_strlen($newDescription ?? '') > mb_strlen($character->ai_description)) {
                $character->ai_description = $newDescription;
            }

            // Only set is_ai_extracted on new entries
            if ($isNew) {
                $character->is_ai_extracted = true;
            }

            $this->resolveFirstAppearance($character, $chapter, $readerOrderCache);

            $character->save();

            $character->chapters()->syncWithoutDetaching([
                $chapter->id => ['role' => $characterData['role'] ?? 'mentioned'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function persistWikiEntries(Book $book, Chapter $chapter, array $entities, EntityNameMatcher $matcher): void
    {
        if (empty($entities)) {
            return;
        }

        $readerOrderCache = [$chapter->id => $chapter->reader_order];

        foreach ($entities as $entityData) {
            if (! is_array($entityData) || empty($entityData['name']) || empty($entityData['kind'])) {
                continue;
            }

            $entry = $matcher->findWikiEntry($entityData['name'], $entityData['kind']);
            $isNew = ! $entry;

            if ($isNew) {
                $entry = $book->wikiEntries()->make([
                    'name' => $entityData['name'],
                    'kind' => $entityData['kind'],
                ]);
            }

            // AI always writes to ai_description, never to description
            $newDescription = $entityData['description'] ?? null;
            if (! $entry->ai_description || mb_strlen($newDescription ?? '') > mb_strlen($entry->ai_description)) {
                $entry->ai_description = $newDescription;
            }

            $entry->type = $entityData['type'] ?? $entry->type;

            // Only set is_ai_extracted on new entries
            if ($isNew) {
                $entry->is_ai_extracted = true;
            }

            $this->resolveFirstAppearance($entry, $chapter, $readerOrderCache);

            $entry->save();

            $entry->chapters()->syncWithoutDetaching([$chapter->id => []]);
        }
    }

    /**
     * Set first_appearance to the earliest chapter by reader_order.
     *
     * @param  array<int, int>  $readerOrderCache
     */
    private function resolveFirstAppearance(Model $entity, Chapter $chapter, array &$readerOrderCache): void
    {
        if ($entity->first_appearance) {
            $currentFirstOrder = $readerOrderCache[$entity->first_appearance]
                ??= Chapter::where('id', $entity->first_appearance)->value('reader_order');
        } else {
            $currentFirstOrder = null;
        }

        if (is_null($currentFirstOrder) || $chapter->reader_order < $currentFirstOrder) {
            $entity->first_appearance = $chapter->id;
        }
    }
}
```

- [ ] **Step 4: Run the new tests to verify they pass**

```bash
php artisan test --compact --filter=EntityExtractorTest
```

Expected: All tests PASS (both new and existing).

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/Concerns/PersistsExtractedEntities.php tests/Feature/Ai/EntityExtractorTest.php
git commit -m "feat: protect manual entries from AI overwrite, write to ai_description with fuzzy matching"
```

---

### Task 5: Update ConsolidateEntities — Manual Entry Protection

**Files:**
- Modify: `app/Jobs/Preparation/ConsolidateEntities.php`
- Modify: `tests/Feature/Jobs/ConsolidateEntitiesTest.php`

- [ ] **Step 1: Write failing tests**

Add these tests to `tests/Feature/Jobs/ConsolidateEntitiesTest.php`:

```php
test('consolidation sees all entries but only merges AI into manual', function () {
    $book = Book::factory()->withAi()->create();
    $chapter1 = Chapter::factory()->for($book)->create(['reader_order' => 1]);
    $chapter2 = Chapter::factory()->for($book)->create(['reader_order' => 2]);

    // Manual entry created by author
    $manual = Character::factory()->for($book)->create([
        'name' => 'Maja Paulsen',
        'aliases' => ['Maja'],
        'description' => 'Author notes about Maja.',
        'ai_description' => null,
        'is_ai_extracted' => false,
    ]);
    $manual->chapters()->attach($chapter1->id, ['role' => 'protagonist']);

    // AI-extracted duplicate
    $aiDuplicate = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Paulsen',
        'aliases' => [],
        'description' => null,
        'ai_description' => 'A young woman from the resistance.',
        'first_appearance' => $chapter2->id,
    ]);
    $aiDuplicate->chapters()->attach($chapter2->id, ['role' => 'supporting']);

    EntityConsolidator::fake(function () use ($manual, $aiDuplicate) {
        return [
            'character_merges' => [
                [
                    'canonical_id' => $manual->id,
                    'duplicate_ids' => [$aiDuplicate->id],
                    'canonical_name' => 'Maja Paulsen',
                    'merged_aliases' => ['Paulsen', 'Maja'],
                ],
            ],
            'entity_merges' => [],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    // Manual entry is preserved as canonical
    $manual->refresh();
    expect($manual->name)->toBe('Maja Paulsen')
        ->and($manual->description)->toBe('Author notes about Maja.')
        ->and($manual->ai_description)->toBe('A young woman from the resistance.')
        ->and($manual->is_ai_extracted)->toBeFalse()
        ->and($manual->aliases)->toContain('Paulsen')
        ->and($manual->first_appearance)->toBe($chapter1->id);

    // AI duplicate is deleted
    expect(Character::find($aiDuplicate->id))->toBeNull();

    // Manual entry has both chapter associations
    expect($manual->chapters()->count())->toBe(2);
});

test('consolidation merges AI wiki entry into manual wiki entry', function () {
    $book = Book::factory()->withAi()->create();
    $chapter1 = Chapter::factory()->for($book)->create(['reader_order' => 1]);

    $manual = WikiEntry::factory()->location()->for($book)->create([
        'name' => 'The Brass Lantern',
        'description' => 'Author notes about the tavern.',
        'ai_description' => null,
        'is_ai_extracted' => false,
        'metadata' => ['aliases' => []],
    ]);
    $manual->chapters()->attach($chapter1->id);

    $aiDuplicate = WikiEntry::factory()->aiExtracted()->location()->for($book)->create([
        'name' => 'Brass Lantern',
        'description' => null,
        'ai_description' => 'A tavern mentioned in chapters 1-3.',
        'metadata' => ['aliases' => ['The Lantern']],
    ]);

    EntityConsolidator::fake(function () use ($manual, $aiDuplicate) {
        return [
            'character_merges' => [],
            'entity_merges' => [
                [
                    'canonical_id' => $manual->id,
                    'duplicate_ids' => [$aiDuplicate->id],
                    'canonical_name' => 'The Brass Lantern',
                    'merged_aliases' => ['Brass Lantern', 'The Lantern'],
                ],
            ],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    $manual->refresh();
    expect($manual->description)->toBe('Author notes about the tavern.')
        ->and($manual->ai_description)->toBe('A tavern mentioned in chapters 1-3.')
        ->and($manual->is_ai_extracted)->toBeFalse()
        ->and($manual->metadata['aliases'])->toContain('Brass Lantern', 'The Lantern');

    expect(WikiEntry::find($aiDuplicate->id))->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
php artisan test --compact --filter="consolidation sees all entries"
php artisan test --compact --filter="consolidation merges AI wiki entry into manual"
```

Expected: FAIL.

- [ ] **Step 3: Update ConsolidateEntities**

In `app/Jobs/Preparation/ConsolidateEntities.php`, make these changes:

**Change the `handle()` method** to load ALL entries (not just AI-extracted):

Replace lines 50-51:
```php
$characters = $this->book->characters()->where('is_ai_extracted', true)->get();
$wikiEntries = $this->book->wikiEntries()->where('is_ai_extracted', true)->get();
```

With:
```php
$characters = $this->book->characters()->get();
$wikiEntries = $this->book->wikiEntries()->get();
```

**Update `applyCharacterMerges()`** — remove `is_ai_extracted` filter on canonical lookup (it can be manual now), keep it on duplicates so only AI entries get deleted, and merge `ai_description`:

Replace the `applyCharacterMerges` method body (inside the `DB::transaction` closure) with:

```php
DB::transaction(function () use ($merge) {
    $canonical = $this->book->characters()->find($merge['canonical_id']);

    if (! $canonical) {
        return;
    }

    $duplicates = $this->book->characters()
        ->whereIn('id', $merge['duplicate_ids'])
        ->get();

    if ($duplicates->isEmpty()) {
        return;
    }

    $canonical->name = $merge['canonical_name'];
    $canonical->aliases = $this->mergeAliases(
        $canonical->aliases ?? [],
        $duplicates->pluck('aliases')->push($duplicates->pluck('name')->all()),
        $merge['canonical_name'],
    );

    $this->keepLongestDescription($canonical, $duplicates);
    $this->keepLongestAiDescription($canonical, $duplicates);
    $this->resolveEarliestAppearance($canonical, $duplicates);
    $canonical->save();

    $canonicalPivots = $canonical->chapters()
        ->withPivot(['role'])
        ->get()
        ->keyBy('id');

    foreach ($duplicates as $duplicate) {
        $syncs = [];

        foreach ($duplicate->chapters()->withPivot(['role'])->get() as $chapter) {
            $newRole = $chapter->pivot->role ?? CharacterRole::Mentioned->value;
            $existing = $canonicalPivots->get($chapter->id);

            if ($existing) {
                $newRole = $this->higherRole($existing->pivot->role ?? CharacterRole::Mentioned->value, $newRole);
            }

            $syncs[$chapter->id] = ['role' => $newRole];
        }

        if (! empty($syncs)) {
            $canonical->chapters()->syncWithoutDetaching($syncs);
            $canonicalPivots = $canonical->chapters()
                ->withPivot(['role'])
                ->get()
                ->keyBy('id');
        }

        $duplicate->chapters()->detach();
        $duplicate->delete();
    }
});
```

**Update `applyEntityMerges()`** — same pattern. Replace the `DB::transaction` closure:

```php
DB::transaction(function () use ($merge) {
    $canonical = $this->book->wikiEntries()->find($merge['canonical_id']);

    if (! $canonical) {
        return;
    }

    $duplicates = $this->book->wikiEntries()
        ->whereIn('id', $merge['duplicate_ids'])
        ->get();

    if ($duplicates->isEmpty()) {
        return;
    }

    $canonical->name = $merge['canonical_name'];

    $metadata = $canonical->metadata ?? [];
    $metadata['aliases'] = $this->mergeAliases(
        $metadata['aliases'] ?? [],
        $duplicates->pluck('metadata')->map(fn ($m) => $m['aliases'] ?? [])->push($duplicates->pluck('name')->all()),
        $merge['canonical_name'],
    );
    $canonical->metadata = $metadata;

    $this->keepLongestDescription($canonical, $duplicates);
    $this->keepLongestAiDescription($canonical, $duplicates);
    $this->resolveEarliestAppearance($canonical, $duplicates);
    $canonical->save();

    foreach ($duplicates as $duplicate) {
        $canonical->chapters()->syncWithoutDetaching(
            $duplicate->chapters()->pluck('chapter_id')
                ->mapWithKeys(fn ($id) => [$id => []])->all()
        );

        $duplicate->chapters()->detach();
        $duplicate->delete();
    }
});
```

**Add the `keepLongestAiDescription()` method** after `keepLongestDescription()`:

```php
/**
 * @param  EloquentCollection<int, Model>  $duplicates
 */
private function keepLongestAiDescription(Model $canonical, EloquentCollection $duplicates): void
{
    foreach ($duplicates as $duplicate) {
        if (mb_strlen($duplicate->ai_description ?? '') > mb_strlen($canonical->ai_description ?? '')) {
            $canonical->ai_description = $duplicate->ai_description;
        }
    }
}
```

**Update `buildPrompt()`** to include both description fields and flag manual entries:

Replace the character line format (line 97) with:

```php
$desc = $character->fullDescription() ?? 'No description';
$source = $character->is_ai_extracted ? '' : ' [MANUAL]';
$lines[] = "- ID:{$character->id} \"{$character->name}\"{$aliases}{$source} — {$desc}";
```

Replace the wiki entry line format (line 106) with:

```php
$desc = $entry->fullDescription() ?? 'No description';
$source = $entry->is_ai_extracted ? '' : ' [MANUAL]';
$lines[] = "- ID:{$entry->id} [{$entry->kind->value}] \"{$entry->name}\"{$aliases}{$source} — {$desc}";
```

- [ ] **Step 4: Run the tests**

```bash
php artisan test --compact --filter=ConsolidateEntitiesTest
```

Expected: All tests PASS (both new and existing).

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/Preparation/ConsolidateEntities.php tests/Feature/Jobs/ConsolidateEntitiesTest.php
git commit -m "feat: consolidation sees all entries, merges AI into manual, preserves manual descriptions"
```

---

### Task 6: Update AI Tools and Agents — Use `fullDescription()`

**Files:**
- Modify: `app/Ai/Tools/LookupExistingEntities.php:31-32,44,54`
- Modify: `app/Ai/Tools/RetrieveManuscriptContext.php:55`
- Modify: `app/Ai/Agents/ProseReviser.php:116-117,139-140`

- [ ] **Step 1: Update LookupExistingEntities**

In `app/Ai/Tools/LookupExistingEntities.php`, change the query on line 31-32 to include `ai_description`:

```php
$characters = $book->characters()->get(['id', 'name', 'aliases', 'description', 'ai_description']);
$wikiEntries = $book->wikiEntries()->get(['id', 'name', 'kind', 'type', 'description', 'ai_description', 'metadata']);
```

Change line 44 to use `fullDescription()`:

```php
$results[] = "- {$character->name}{$aliases}: {$character->fullDescription()}";
```

Change line 54 to use `fullDescription()`:

```php
$results[] = "- [{$entry->kind->value}] {$entry->name}{$aliases}{$type}: {$entry->fullDescription()}";
```

- [ ] **Step 2: Update RetrieveManuscriptContext**

In `app/Ai/Tools/RetrieveManuscriptContext.php`, change line 55:

```php
$sections[] = "- {$character->name}{$aliases}: {$character->fullDescription()}";
```

- [ ] **Step 3: Update ProseReviser**

In `app/Ai/Agents/ProseReviser.php`, change lines 116-117:

```php
if ($character->fullDescription()) {
    $line .= ": {$character->fullDescription()}";
}
```

Change lines 139-140:

```php
if ($entry->fullDescription()) {
    $line .= ": {$entry->fullDescription()}";
}
```

- [ ] **Step 4: Run existing tests**

```bash
php artisan test --compact --filter=EntityExtractorTest
php artisan test --compact --filter=ConsolidateEntitiesTest
```

Expected: All PASS.

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Tools/LookupExistingEntities.php app/Ai/Tools/RetrieveManuscriptContext.php app/Ai/Agents/ProseReviser.php
git commit -m "feat: AI tools and agents use fullDescription() for combined manual + AI context"
```

---

### Task 7: Update WikiController — Manual Edits Stay in `description`

**Files:**
- Modify: `app/Http/Controllers/WikiController.php`

- [ ] **Step 1: Verify current behavior is already correct**

The controller already sets `is_ai_extracted => false` on `storeCharacter` (line 71) and `storeEntry` (line 109). The `updateCharacter` and `updateEntry` methods use `$request->safe()` / `$request->validated()` which only include validated fields — `ai_description` is NOT in the form request rules, so it will never be touched by manual updates.

No changes needed to the controller itself. The form requests already exclude `ai_description` from validation rules, so it's naturally protected.

- [ ] **Step 2: Verify with a quick test run**

```bash
php artisan test --compact --filter=WikiEntryCreationTest
php artisan test --compact --filter=WikiPageTest
```

Expected: All PASS.

- [ ] **Step 3: Commit (skip if no changes)**

No commit needed — controller is already correct.

---

### Task 8: Update TypeScript Types

**Files:**
- Modify: `resources/js/types/models.ts`

- [ ] **Step 1: Add `ai_description` to Character type**

In `resources/js/types/models.ts`, find the `Character` type (around line 220) and add `ai_description` after `description`:

```typescript
export type Character = {
    id: number;
    book_id: number;
    name: string;
    aliases: string[] | null;
    description: string | null;
    ai_description: string | null;  // <-- add this line
    first_appearance: number | null;
    storylines: number[] | null;
    is_ai_extracted: boolean;
    created_at: string;
    updated_at: string;
    book?: Book;
    first_appearance_chapter?: Chapter;
    chapters?: (Chapter & { pivot: CharacterChapterPivot })[];
};
```

- [ ] **Step 2: Add `ai_description` to WikiEntry type**

Find the `WikiEntry` type (around line 574) and add `ai_description` after `description`:

```typescript
export type WikiEntry = {
    id: number;
    book_id: number;
    kind: WikiEntryKind;
    name: string;
    type: string | null;
    description: string | null;
    ai_description: string | null;  // <-- add this line
    first_appearance: number | null;
    metadata: Record<string, unknown> | null;
    is_ai_extracted: boolean;
    created_at: string;
    updated_at: string;
    first_appearance_chapter?: Chapter;
    chapters?: Chapter[];
};
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/types/models.ts
git commit -m "feat: add ai_description to Character and WikiEntry TypeScript types"
```

---

### Task 9: Update Frontend — Detail Views Show Both Descriptions

**Files:**
- Modify: `resources/js/components/wiki/CharacterDetail.tsx`
- Modify: `resources/js/components/wiki/WikiEntryDetail.tsx`
- Modify: `resources/js/i18n/en/wiki.json`

- [ ] **Step 1: Add translation keys**

In `resources/js/i18n/en/wiki.json`, add these keys (insert after the `"description"` line):

```json
"description.author": "Your Notes",
"description.ai": "From Manuscript",
```

- [ ] **Step 2: Update CharacterDetail**

In `resources/js/components/wiki/CharacterDetail.tsx`, replace the Description section (lines 71-84) with:

```tsx
{/* Author Description */}
{character.description && (
    <Card className="flex flex-col gap-3 p-6">
        <SectionLabel>{t('description.author')}</SectionLabel>
        <div className="flex flex-col gap-3 text-[14px] leading-relaxed text-ink">
            {character.description
                .split('\n')
                .filter(Boolean)
                .map((paragraph, i) => (
                    <p key={i}>{paragraph}</p>
                ))}
        </div>
    </Card>
)}

{/* AI Description */}
{character.ai_description && (
    <Card className="flex flex-col gap-3 border-border-subtle bg-surface-base p-6">
        <SectionLabel>{t('description.ai')}</SectionLabel>
        <div className="flex flex-col gap-3 text-[14px] leading-relaxed text-ink-muted">
            {character.ai_description
                .split('\n')
                .filter(Boolean)
                .map((paragraph, i) => (
                    <p key={i}>{paragraph}</p>
                ))}
        </div>
    </Card>
)}
```

- [ ] **Step 3: Update WikiEntryDetail**

In `resources/js/components/wiki/WikiEntryDetail.tsx`, replace the Description section (lines 49-62) with:

```tsx
{/* Author Description */}
{entry.description && (
    <Card className="flex flex-col gap-3 p-6">
        <SectionLabel>{t('description.author')}</SectionLabel>
        <div className="flex flex-col gap-3 text-[14px] leading-relaxed text-ink">
            {entry.description
                .split('\n')
                .filter(Boolean)
                .map((paragraph, i) => (
                    <p key={i}>{paragraph}</p>
                ))}
        </div>
    </Card>
)}

{/* AI Description */}
{entry.ai_description && (
    <Card className="flex flex-col gap-3 border-border-subtle bg-surface-base p-6">
        <SectionLabel>{t('description.ai')}</SectionLabel>
        <div className="flex flex-col gap-3 text-[14px] leading-relaxed text-ink-muted">
            {entry.ai_description
                .split('\n')
                .filter(Boolean)
                .map((paragraph, i) => (
                    <p key={i}>{paragraph}</p>
                ))}
        </div>
    </Card>
)}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/wiki/CharacterDetail.tsx resources/js/components/wiki/WikiEntryDetail.tsx resources/js/i18n/en/wiki.json
git commit -m "feat: show author notes and AI description as separate cards in wiki detail views"
```

---

### Task 10: Update Frontend — WikiForm Shows Read-Only AI Description

**Files:**
- Modify: `resources/js/components/wiki/WikiForm.tsx`

- [ ] **Step 1: Update WikiForm to show AI description in edit mode**

In `resources/js/components/wiki/WikiForm.tsx`, after the Description `FormField` section (after line 414, before the Storylines section), add a read-only AI description block:

```tsx
{/* AI Description (read-only, edit mode only) */}
{isEditing && item?.ai_description && (
    <div className="flex flex-col gap-2">
        <span className={wikiLabelClass}>
            {t('description.ai')}
        </span>
        <div className="rounded-md border border-border-subtle bg-surface-base px-3 py-2.5">
            <div className="flex flex-col gap-2 text-[13px] leading-relaxed text-ink-muted">
                {item.ai_description
                    .split('\n')
                    .filter(Boolean)
                    .map((paragraph, i) => (
                        <p key={i}>{paragraph}</p>
                    ))}
            </div>
        </div>
    </div>
)}
```

Also update the Description field label to say "Your Notes" when AI description exists. Replace the Description `FormField` (lines 388-414):

```tsx
{/* Description (author's manual notes) */}
<FormField
    label={
        isEditing && item?.ai_description
            ? t('description.author')
            : t('field.description')
    }
    labelClassName={wikiLabelClass}
>
    <Textarea
        value={
            isCharacter
                ? characterForm.data.description
                : entryForm.data.description
        }
        onChange={(e) =>
            isCharacter
                ? characterForm.setData(
                      'description',
                      e.target.value,
                  )
                : entryForm.setData('description', e.target.value)
        }
        placeholder={
            isCharacter
                ? t('field.descriptionPlaceholder')
                : t('field.entryDescriptionPlaceholder')
        }
        rows={isEditing ? 8 : 6}
    />
</FormField>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/wiki/WikiForm.tsx
git commit -m "feat: show read-only AI description in wiki form, label author notes separately"
```

---

### Task 11: Update Existing Tests for Dual-Description

**Files:**
- Modify: `tests/Feature/Ai/EntityExtractorTest.php`
- Modify: `tests/Feature/Jobs/ConsolidateEntitiesTest.php`

- [ ] **Step 1: Update existing EntityExtractorTest assertions**

In `tests/Feature/Ai/EntityExtractorTest.php`, the existing tests reference `description` on AI-created entries. Since AI now writes to `ai_description`, update these:

In the test `'extract entities job creates character and wiki entry records'` (around line 42):
- The assertion `$hans->is_ai_extracted->toBeTrue()` stays
- Add: `->and($hans->ai_description)->toBe('The protagonist')`
- Add: `->and($hans->description)->toBeNull()`

In the test `'extract entities job keeps longer description'` (around line 163):
- Change `expect($hans->description)->toBe($longDescription)` to `expect($hans->ai_description)->toBe($longDescription)`
- The `$longDescription` variable should be set as existing `ai_description` instead of `description`:

```php
$book->characters()->create([
    'name' => 'Hans Mueller',
    'aliases' => [],
    'description' => null,
    'ai_description' => $longDescription,
    'is_ai_extracted' => true,
]);
```

In the test `'extract entities job merges aliases instead of overwriting'` (around line 122):
- Update the initial character creation to use `ai_description` instead of `description`:

```php
$book->characters()->create([
    'name' => 'Hans Mueller',
    'aliases' => ['Hans', 'Herr Mueller'],
    'description' => null,
    'ai_description' => 'The protagonist',
    'is_ai_extracted' => true,
]);
```

- [ ] **Step 2: Update existing ConsolidateEntitiesTest assertions**

In `tests/Feature/Jobs/ConsolidateEntitiesTest.php`, update character and wiki entry factories to use `ai_description`:

In test `'merges duplicate characters'`:
- Change `'description' => 'A young woman who leads the resistance.'` to `'ai_description' => 'A young woman who leads the resistance.', 'description' => null`
- Change `'description' => 'Short desc.'` to `'ai_description' => 'Short desc.', 'description' => null`
- Change assertion `$canonical->description` to `$canonical->ai_description`

In test `'merges duplicate wiki entries'`:
- Change both entries to use `ai_description` instead of `description`
- Update the assertion accordingly

In test `'keeps longer description when merging'`:
- Change to use `ai_description` instead of `description`
- Update assertions to check `ai_description`

- [ ] **Step 3: Run all affected tests**

```bash
php artisan test --compact --filter=EntityExtractorTest
php artisan test --compact --filter=ConsolidateEntitiesTest
php artisan test --compact --filter=WikiEntryTest
php artisan test --compact --filter=WikiPageTest
```

Expected: All PASS.

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Ai/EntityExtractorTest.php tests/Feature/Jobs/ConsolidateEntitiesTest.php
git commit -m "test: update existing tests for dual-description model"
```

---

### Task 12: Final Verification

- [ ] **Step 1: Run the full test suite for affected areas**

```bash
php artisan test --compact --filter=EntityExtractorTest
php artisan test --compact --filter=ConsolidateEntitiesTest
php artisan test --compact --filter=EntityNameMatcherTest
php artisan test --compact --filter=WikiEntryTest
php artisan test --compact --filter=WikiEntryCreationTest
php artisan test --compact --filter=WikiPageTest
```

Expected: All PASS.

- [ ] **Step 2: Run Pint on all dirty files**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Build frontend**

```bash
npm run build
```

Expected: No TypeScript errors.

- [ ] **Step 4: Final commit if any formatting changes**

```bash
git add -A && git commit -m "style: apply pint formatting" || echo "Nothing to commit"
```

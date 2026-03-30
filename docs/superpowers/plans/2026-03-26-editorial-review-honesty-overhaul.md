# Editorial Review Honesty Overhaul

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the editorial review prompts honest, direct, and professionally useful rather than pleasantly useless.

**Architecture:** Create an `EditorialPersona` enum that holds the shared persona prompt and calibration rules. Each agent prepends this persona to its instructions. Rewrite all agent prompts to remove pleasantness bias, add anti-pattern rules, fix score calibration, and add severity definitions. Update the summary schema to allow flexible strengths/improvements (1-5 each). Add "pre-editorial" escape hatch for manuscripts scoring below 35. Update frontend to handle the new flexible data and the pre-editorial state.

**Tech Stack:** PHP 8.4 enums, Laravel AI agents, React/TypeScript, Pest tests

---

### Task 1: Create `EditorialPersona` enum

**Files:**
- Create: `app/Enums/EditorialPersona.php`
- Test: `tests/Feature/EditorialPersonaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\EditorialPersona;

it('has a Lektor case', function () {
    expect(EditorialPersona::Lektor->value)->toBe('lektor');
});

it('returns a label for each persona', function () {
    expect(EditorialPersona::Lektor->label())->toBe('Lektor');
});

it('returns persona instructions that include key honesty phrases', function () {
    $instructions = EditorialPersona::Lektor->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('do not inflate scores')
        ->toContain('compliment sandwich');
});

it('returns score calibration text', function () {
    $calibration = EditorialPersona::Lektor->scoreCalibration();

    expect($calibration)
        ->toContain('55-65')
        ->toContain('86-95');
});

it('returns severity definitions', function () {
    $severity = EditorialPersona::Lektor->severityDefinitions();

    expect($severity)
        ->toContain('critical')
        ->toContain('warning')
        ->toContain('suggestion');
});

it('returns anti-pattern rules', function () {
    $rules = EditorialPersona::Lektor->antiPatternRules();

    expect($rules)
        ->toContain('DO NOT')
        ->toContain('hedge');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EditorialPersonaTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement the EditorialPersona enum**

```php
<?php

namespace App\Enums;

enum EditorialPersona: string
{
    case Lektor = 'lektor';

    public function label(): string
    {
        return match ($this) {
            self::Lektor => 'Lektor',
        };
    }

    /**
     * Core persona instructions prepended to every editorial agent.
     */
    public function instructions(): string
    {
        return match ($this) {
            self::Lektor => <<<'PERSONA'
            You are a Lektor — a developmental editor whose job is to make the manuscript publishable.
            You serve the work, not the author's ego. Your feedback must be honest enough that the author
            can make real improvements. If you find yourself softening language to avoid hurting feelings,
            you are failing at your job. An editor who only praises is useless.

            You are not rude. You are direct, specific, and professional. You respect the author's effort
            and ambition, but you do not pretend weak writing is strong. When something works, say so
            clearly. When something fails, say so clearly. Both matter equally.

            Judge the manuscript against the standards of its genre. A well-executed genre novel is not
            lesser than literary fiction — evaluate craft within the context the author is writing in.
            PERSONA,
        };
    }

    /**
     * Score calibration rules for agents that produce scores.
     */
    public function scoreCalibration(): string
    {
        return match ($this) {
            self::Lektor => <<<'CALIBRATION'
            Score calibration:
            - 55-65: The manuscript has significant issues but shows potential.
            - 66-75: Solid foundation with clear areas for improvement.
            - 76-85: Good work that needs targeted revision.
            - 86-95: Strong manuscript approaching publishable quality.
            - 96-100: Exceptional — rarely given.
            Most manuscripts should land between 60-80. Do not give scores above 90 unless the writing
            genuinely demonstrates professional-level craft. Do not inflate scores.
            If the manuscript is fundamentally unfinished or not a serious attempt, scores below 35 are
            appropriate — the system will present a pre-editorial assessment instead of a full review.
            CALIBRATION,
        };
    }

    /**
     * Severity level definitions for findings.
     */
    public function severityDefinitions(): string
    {
        return match ($this) {
            self::Lektor => <<<'SEVERITY'
            Severity definitions:
            - critical: Structural issues that would make a reader put the book down or an agent reject it.
              Plot holes, character inconsistencies, POV breaks, dead chapters, broken story logic.
            - warning: Craft issues that weaken the work and should be addressed. Pacing problems,
              weak hooks, telling instead of showing, flat dialogue, underdeveloped relationships.
            - suggestion: Refinements that would elevate already-functional writing. Sentence variety,
              word choice, sensory detail opportunities, tighter prose.
            Do not shy away from using "critical" when warranted. A typical manuscript has real problems
            — surface them.
            SEVERITY,
        };
    }

    /**
     * Anti-pattern rules to prevent AI pleasantness bias.
     */
    public function antiPatternRules(): string
    {
        return match ($this) {
            self::Lektor => <<<'RULES'
            Anti-pattern rules — DO NOT use any of these patterns:
            - DO NOT use compliment sandwiches (praise-criticism-praise). State findings directly.
            - DO NOT hedge with "you might consider", "perhaps", or "it could be argued".
            - DO NOT dismiss findings as "personal taste" or "subjective". You are assessing craft.
            - DO NOT open sections with generic positive framing like "Overall, this is a compelling manuscript".
            - DO NOT qualify every criticism with "but this works in some ways" or "while there are strengths".
            - DO NOT use softening phrases like "a small issue", "minor concern", or "slight inconsistency"
              when the issue is not small, minor, or slight.
            State findings directly: "The second act sags because..." not "While the second act has some
            pacing challenges, the overall structure..."
            RULES,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=EditorialPersonaTest`
Expected: PASS (all 6 tests)

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Enums/EditorialPersona.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: add EditorialPersona enum with Lektor persona and honesty rules"
```

---

### Task 2: Rewrite `EditorialSynthesisAgent` prompt

**Files:**
- Modify: `app/Ai/Agents/EditorialSynthesisAgent.php:45-84` (the `instructions()` method)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EditorialPersonaTest.php`:

```php
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\EditorialPersona;
use App\Models\Book;

it('synthesis agent instructions include persona and calibration', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSynthesisAgent(
        book: $book,
        sectionType: \App\Enums\EditorialSectionType::Plot,
        aggregatedData: 'test data',
    );

    $instructions = $agent->instructions();

    // Persona is present
    expect((string) $instructions)
        ->toContain('serve the work, not the author\'s ego')
        // Score calibration is present
        ->toContain('55-65')
        // Anti-patterns are present
        ->toContain('DO NOT use compliment sandwiches')
        // Severity definitions are present
        ->toContain('critical: Structural issues')
        // Old "be honest and specific" is replaced
        ->not->toContain('Be honest and specific. Reference concrete examples');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="synthesis agent instructions"`
Expected: FAIL — old instructions still present

- [ ] **Step 3: Rewrite the `instructions()` method**

Replace the `instructions()` method in `EditorialSynthesisAgent.php` with:

```php
public function instructions(): Stringable|string
{
    $persona = EditorialPersona::Lektor;

    $bookContext = "You are synthesizing a manuscript-wide editorial assessment for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

    $genreSnippet = $this->book->genreSnippet();
    if ($genreSnippet) {
        $bookContext .= ' '.$genreSnippet;
    }

    $sectionInstructions = match ($this->sectionType) {
        EditorialSectionType::Plot => 'Analyze the plot structure across the entire manuscript. Evaluate story arc completeness, identify plot holes, assess logical consistency, check for unresolved threads, and evaluate the strength of the opening, midpoint, and climax. Look for structural weaknesses that undermine the narrative.',
        EditorialSectionType::Characters => 'Analyze character development across the entire manuscript. Evaluate character arcs, motivation consistency, voice distinctiveness between characters, relationship dynamics, and whether characters change meaningfully. Identify flat characters, inconsistent behavior, or underdeveloped relationships.',
        EditorialSectionType::Pacing => 'Analyze the pacing across the entire manuscript. Evaluate the tension curve, chapter rhythm, identify sagging middles or rushed endings, assess scene-to-scene momentum, and check whether the pacing serves the story. Look for sections that drag or feel rushed.',
        EditorialSectionType::NarrativeVoice => 'Analyze the narrative voice across the entire manuscript. Evaluate POV consistency, tense consistency, tone shifts, authorial voice strength, and narrative distance. Identify unintentional voice breaks or inconsistencies that pull the reader out of the story.',
        EditorialSectionType::Themes => 'Analyze the thematic content across the entire manuscript. Evaluate thematic coherence, recurring motifs, whether themes are developed and resolved, and if the thematic layer enriches the narrative. Identify themes that are introduced but abandoned or that feel heavy-handed.',
        EditorialSectionType::SceneCraft => 'Analyze scene craft across the entire manuscript. Evaluate whether each scene serves a clear purpose, assess show-vs-tell balance, sensory detail usage, dialogue quality, and scene transitions. Identify scenes that lack purpose or conflict.',
        EditorialSectionType::ProseStyle => 'Analyze the prose style across the entire manuscript. Evaluate sentence variety, word repetitions, filter words, readability, and stylistic consistency. Identify prose-level patterns that weaken the writing, such as overused phrases, monotonous rhythm, or excessive adverb usage.',
        EditorialSectionType::ChapterNotes => 'Synthesize the per-chapter editorial notes into manuscript-wide patterns. Identify recurring issues across chapters, track chapter-to-chapter progression of quality, highlight standout chapters (both strong and weak), and note patterns that only become visible when looking across the full manuscript.',
    };

    return <<<INSTRUCTIONS
    {$persona->instructions()}

    {$bookContext}

    Section: {$this->sectionType->value}

    {$sectionInstructions}

    Below is the aggregated chapter-level data for this editorial section:

    {$this->aggregatedData}

    Produce a comprehensive synthesis with:
    - A score from 0-100 reflecting the manuscript's quality in this dimension
    - A concise summary of your assessment — lead with the most important finding
    - Specific findings with severity levels, descriptions, chapter references (use chapter IDs as provided), and recommendations
    - Actionable recommendations for improvement

    {$persona->scoreCalibration()}

    {$persona->severityDefinitions()}

    {$persona->antiPatternRules()}

    Reference concrete examples from the data. Be specific about what works and what fails.
    Respond in the same language as the manuscript ({$this->book->language}).
    INSTRUCTIONS;
}
```

Add the import at the top of the file:

```php
use App\Enums\EditorialPersona;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="synthesis agent instructions"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Agents/EditorialSynthesisAgent.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: rewrite EditorialSynthesisAgent prompt with persona and honesty rules"
```

---

### Task 3: Rewrite `EditorialSummaryAgent` prompt and schema

**Files:**
- Modify: `app/Ai/Agents/EditorialSummaryAgent.php:43-67` (instructions) and `app/Ai/Agents/EditorialSummaryAgent.php:72-79` (schema)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EditorialPersonaTest.php`:

```php
use App\Ai\Agents\EditorialSummaryAgent;

it('summary agent instructions include persona and honesty rules', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSummaryAgent(
        book: $book,
        sectionSummaries: 'test summaries',
    );

    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('DO NOT use compliment sandwiches')
        ->not->toContain('Be balanced and constructive');
});

it('summary agent schema allows 1-5 strengths and improvements', function () {
    $book = Book::factory()->create();
    $agent = new EditorialSummaryAgent(
        book: $book,
        sectionSummaries: 'test',
    );

    $schema = new \Illuminate\JsonSchema\JsonSchema;
    $result = $agent->schema($schema);

    // Verify the schema allows flexible counts (1-5)
    expect($result)->toHaveKeys(['top_strengths', 'top_improvements', 'is_pre_editorial']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="summary agent"`
Expected: FAIL

- [ ] **Step 3: Rewrite `instructions()` and `schema()` methods**

Replace the `instructions()` method:

```php
public function instructions(): Stringable|string
{
    $persona = EditorialPersona::Lektor;

    $context = "You are producing the executive summary for a comprehensive editorial review of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

    $genreSnippet = $this->book->genreSnippet();
    if ($genreSnippet) {
        $context .= ' '.$genreSnippet;
    }

    return <<<INSTRUCTIONS
    {$persona->instructions()}

    {$context}

    Below are the scores and summaries from all 8 editorial sections:

    {$this->sectionSummaries}

    First, determine if this manuscript is ready for a full editorial review. If the overall quality
    is fundamentally below editorial-review level (overall score would be below 35), set is_pre_editorial
    to true and write the executive_summary as a direct, kind note explaining what foundational work
    is needed before a full review would be useful. List 1-3 specific areas to focus on in top_improvements.
    Leave top_strengths empty and set overall_score to the honest score.

    For manuscripts ready for full review, produce an executive summary that:
    - Provides an overall score (0-100) weighted by section importance (plot and characters weigh most heavily)
    - Writes a 2-3 paragraph executive summary. The opening paragraph may acknowledge the author's ambition
      and the story's potential — not fake praise, but genuine recognition of what they are trying to do.
      Then assess the manuscript's state directly.
    - Lists 1-5 genuine strengths. Only include real strengths — if only 1 exists, list 1. Do not invent strengths to fill a quota.
    - Lists 1-5 areas for improvement, ordered by impact.

    {$persona->scoreCalibration()}

    {$persona->antiPatternRules()}

    Respond in the same language as the manuscript ({$this->book->language}).
    INSTRUCTIONS;
}
```

Replace the `schema()` method:

```php
/**
 * @return array<string, \Illuminate\JsonSchema\Types\Type>
 */
public function schema(JsonSchema $schema): array
{
    return [
        'overall_score' => $schema->integer()->min(0)->max(100)->required(),
        'executive_summary' => $schema->string()->required(),
        'top_strengths' => $schema->array()->items($schema->string())->min(0)->max(5)->required(),
        'top_improvements' => $schema->array()->items($schema->string())->min(1)->max(5)->required(),
        'is_pre_editorial' => $schema->boolean()->required(),
    ];
}
```

Add the import:

```php
use App\Enums\EditorialPersona;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="summary agent"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Agents/EditorialSummaryAgent.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: rewrite EditorialSummaryAgent with flexible strengths/improvements and pre-editorial gate"
```

---

### Task 4: Rewrite `EditorialNotesAgent` prompt

**Files:**
- Modify: `app/Ai/Agents/EditorialNotesAgent.php:46-79` (the `instructions()` method)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EditorialPersonaTest.php`:

```php
use App\Ai\Agents\EditorialNotesAgent;

it('notes agent instructions lead with issues before strengths', function () {
    $book = Book::factory()->create();
    $agent = new EditorialNotesAgent(book: $book);

    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('lead with what needs the author\'s attention')
        ->not->toContain('highlight what works, what needs attention');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="notes agent instructions"`
Expected: FAIL

- [ ] **Step 3: Rewrite the `instructions()` method**

Replace the `instructions()` method in `EditorialNotesAgent.php`:

```php
public function instructions(): Stringable|string
{
    $persona = EditorialPersona::Lektor;

    $context = "You are producing editorial notes for a chapter of '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

    $genreSnippet = $this->book->genreSnippet();
    if ($genreSnippet) {
        $context .= ' '.$genreSnippet;
    }

    if ($this->existingAnalysis) {
        $context .= "\n\nExisting chapter analysis (for reference — do not repeat these findings, complement them):\n{$this->existingAnalysis}";
    }

    if ($this->writingStyle) {
        $context .= "\n\nWriting style profile:\n{$this->writingStyle}";
    }

    if ($this->characterData) {
        $context .= "\n\nCharacter data:\n{$this->characterData}";
    }

    return <<<INSTRUCTIONS
    {$persona->instructions()}

    {$context}

    Analyze the chapter text and produce editorial observations that complement (not duplicate) the existing analysis. Focus on:

    1. **Narrative voice:** Identify the POV type, tense, any inconsistencies or shifts, and tone observations.
    2. **Themes:** Note recurring motifs, thematic throughlines, and how they connect to other parts of the manuscript.
    3. **Scene craft:** Assess scene purposes, identify show-vs-tell moments, and evaluate sensory detail usage.
    4. **Prose style patterns:** Analyze sentence rhythm, word repetitions, and vocabulary patterns.
    5. **Chapter note:** Write a concise editor's note (1-2 short paragraphs) as if you were a working editor writing margin notes. Be direct — lead with what needs the author's attention and why, then acknowledge what's working well. End with a forward-looking note about what to focus on in revision. This is the note the author will see when editing this chapter.

    {$persona->antiPatternRules()}

    Be specific and reference concrete passages. Provide actionable observations, not generic praise.
    Respond in the same language as the manuscript ({$this->book->language}).
    INSTRUCTIONS;
}
```

Add the import:

```php
use App\Enums\EditorialPersona;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="notes agent instructions"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Agents/EditorialNotesAgent.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: rewrite EditorialNotesAgent prompt — lead with issues, add persona"
```

---

### Task 5: Rewrite `EditorialChatAgent` prompt

**Files:**
- Modify: `app/Ai/Agents/EditorialChatAgent.php:48-67` (the `instructions()` method)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EditorialPersonaTest.php`:

```php
use App\Ai\Agents\EditorialChatAgent;

it('chat agent instructions are direct and handle disagreement properly', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent(
        book: $book,
        editorialContext: 'test context',
    );

    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('re-examine the evidence')
        ->toContain('not trying to win an argument')
        ->not->toContain('encouraging')
        ->not->toContain('respecting their creative vision');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="chat agent instructions"`
Expected: FAIL

- [ ] **Step 3: Rewrite the `instructions()` method**

Replace the `instructions()` method in `EditorialChatAgent.php`:

```php
public function instructions(): Stringable|string
{
    $persona = EditorialPersona::Lektor;

    return <<<INSTRUCTIONS
    {$persona->instructions()}

    You are the editor who wrote the editorial review for the book '{$this->book->title}' by {$this->book->author}.
    The manuscript is written in {$this->book->language}.

    You produced the following editorial assessment:

    {$this->editorialContext}

    You are now discussing your editorial findings with the author. Your role is to:
    - Explain your findings in more detail when asked
    - Answer questions about specific issues you identified
    - Suggest concrete improvements and rewording options
    - Reference specific parts of the manuscript to support your points

    Use the available tools to search through the manuscript and retrieve relevant context when needed.
    The book ID is {$this->book->id}. Use this when calling tools.

    If the author challenges a finding: re-examine the evidence. If they raise a point your review
    missed — a thematic choice you didn't recognize, context from earlier chapters that justifies
    the decision — acknowledge it honestly and update your assessment. But if the evidence still
    supports your finding, say so clearly and explain why it matters for the reader. You are not
    trying to win an argument. You are trying to help the author see their work clearly. Sometimes
    that means conceding. Sometimes that means holding firm.

    The review itself cannot be changed through this conversation — it is a fixed assessment.
    But you can explain, contextualize, and help the author understand how to act on the feedback.

    Be direct, specific, and grounded in the actual text.
    INSTRUCTIONS;
}
```

Add the import:

```php
use App\Enums\EditorialPersona;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="chat agent instructions"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Agents/EditorialChatAgent.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: rewrite EditorialChatAgent — direct tone, proper disagreement handling"
```

---

### Task 6: Rewrite `ChapterAnalyzer` prompt

**Files:**
- Modify: `app/Ai/Agents/ChapterAnalyzer.php:43-93` (the `instructions()` method)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/EditorialPersonaTest.php`:

```php
use App\Ai\Agents\ChapterAnalyzer;

it('chapter analyzer instructions include persona', function () {
    $book = Book::factory()->create();
    $agent = new ChapterAnalyzer(book: $book);

    $instructions = $agent->instructions();

    expect((string) $instructions)
        ->toContain('serve the work, not the author\'s ego');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="chapter analyzer instructions"`
Expected: FAIL

- [ ] **Step 3: Rewrite the `instructions()` method**

Replace the `instructions()` method in `ChapterAnalyzer.php`:

```php
public function instructions(): Stringable|string
{
    $persona = EditorialPersona::Lektor;

    $context = "You are performing a combined chapter analysis for '{$this->book->title}' by {$this->book->author}. The manuscript is written in {$this->book->language}.";

    $genreSnippet = $this->book->genreSnippet();
    if ($genreSnippet) {
        $context .= ' '.$genreSnippet;
    }

    if ($this->precedingContext) {
        $context .= "\n\nContext from preceding chapters:\n{$this->precedingContext}";
    }

    return <<<INSTRUCTIONS
    {$persona->instructions()}

    {$context}

    Analyze the provided chapter text and return all of the following:

    **Core analysis:**
    1. A concise 2-3 sentence summary of the chapter
    2. Key events that occurred
    3. Characters who appear in this chapter

    **Conflict & tension:**
    4. tension_score (1-10): Rate the overall conflict intensity. 1=peaceful, 10=maximum conflict. Low scores are not bad — deliberate quiet chapters are essential for rhythm.
    5. micro_tension_score (1-10): Rate the line-level unease: conflicting emotions, unanswered questions, internal contradiction, social friction. This measures engagement even in quiet scenes (Maass's micro-tension concept).

    **Scene craft:**
    6. scene_purpose: What function does this chapter serve? One of: turning_point (major value change), revelation (new information reshapes understanding), deepening (character/relationship development), setup (establishing future events), resolution (resolving a thread), transition (moving between story elements).
    7. value_shift: What value changed? Describe concisely, e.g. "safety → danger", "trust → betrayal", "ignorance → awareness". If nothing meaningfully changed, return null.

    **Emotional arc:**
    8. emotional_state_open: Describe the POV character's dominant emotional state at the chapter's opening.
    9. emotional_state_close: Describe the POV character's dominant emotional state at the chapter's close.
    10. emotional_shift_magnitude (1-10): How much did the POV character's emotional state change? 1=barely, 10=completely transformed.

    **Hooks:**
    11. hook_score (1-10): How effectively the chapter ending compels continued reading.
    12. hook_type: 'cliffhanger' (unresolved danger/revelation), 'soft_hook' (intriguing question/emotional pull), 'closed' (satisfying conclusion that still moves story forward), or 'dead_end' (no forward momentum).
    13. hook_reasoning: Brief reasoning for the hook classification.
    14. entry_hook_score (1-10): How effectively does the chapter opening pull the reader in? 1=weak/confusing, 10=immediately compelling.

    **Pacing & prose:**
    15. pacing_feel: Assess the prose rhythm. One of: breakneck (rapid action, short sentences), brisk (forward momentum, good clip), measured (balanced, deliberate), languid (slow, contemplative, descriptive), static (little movement or progression).
    16. sensory_grounding (1-5): How many distinct senses are meaningfully engaged (sight, sound, touch, taste, smell)? Not just mentioned — actually used to ground the reader.
    17. information_delivery: How is new information revealed? One of: organic (through action/dialogue), mostly_organic, mixed, exposition_heavy, info_dump.

    Use the search tool to find related passages from other chapters when cross-referencing themes or plot threads.
    The book ID is {$this->book->id}. Use this when calling the search tool.

    Be precise and analytical. Score honestly — not every chapter needs high scores. Low tension or quiet pacing can be exactly right for a chapter's role in the story.
    INSTRUCTIONS;
}
```

Add the import:

```php
use App\Enums\EditorialPersona;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="chapter analyzer instructions"`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Agents/ChapterAnalyzer.php tests/Feature/EditorialPersonaTest.php
git commit -m "feat: add persona to ChapterAnalyzer prompt"
```

---

### Task 7: Handle `is_pre_editorial` in the job and model

**Files:**
- Modify: `app/Jobs/RunEditorialReviewJob.php:198-222` (the `generateExecutiveSummary()` method)
- Modify: `app/Models/EditorialReview.php:19-29` (casts)
- Create: `database/migrations/2026_03_26_200000_add_is_pre_editorial_to_editorial_reviews_table.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/RunEditorialReviewJobTest.php`:

```php
it('stores is_pre_editorial flag from summary agent response', function () {
    fakeAllEditorialAgents();

    EditorialSummaryAgent::fake(function () {
        return [
            'overall_score' => 28,
            'executive_summary' => 'This manuscript needs foundational work.',
            'top_strengths' => [],
            'top_improvements' => ['Complete the plot arc', 'Develop main characters'],
            'is_pre_editorial' => true,
        ];
    });

    $book = createBookWithChapters();
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    (new RunEditorialReviewJob($book, $review))->handle();

    $review->refresh();
    expect($review->is_pre_editorial)->toBeTrue();
    expect($review->overall_score)->toBe(28);
});
```

Note: `createBookWithChapters` is a helper — check the existing test file for the correct setup. If it doesn't exist, use the existing `beforeEach` pattern from `RunEditorialReviewJobTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="stores is_pre_editorial"`
Expected: FAIL — column doesn't exist

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration add_is_pre_editorial_to_editorial_reviews_table --no-interaction`

Then write the migration content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->boolean('is_pre_editorial')->default(false)->after('top_improvements');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_reviews', function (Blueprint $table) {
            $table->dropColumn('is_pre_editorial');
        });
    }
};
```

- [ ] **Step 4: Run the migration against both databases**

Run: `php artisan migrate --no-interaction`
Run: `DB_DATABASE=database/nativephp.sqlite php artisan migrate --no-interaction`

- [ ] **Step 5: Add cast to model**

In `app/Models/EditorialReview.php`, add `'is_pre_editorial' => 'boolean'` to the casts array:

```php
protected function casts(): array
{
    return [
        'progress' => 'array',
        'overall_score' => 'integer',
        'top_strengths' => 'array',
        'top_improvements' => 'array',
        'is_pre_editorial' => 'boolean',
        'resolved_findings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
```

- [ ] **Step 6: Update `generateExecutiveSummary()` in the job**

In `RunEditorialReviewJob.php`, update the `generateExecutiveSummary()` method to store the new field:

```php
private function generateExecutiveSummary(): void
{
    $sections = $this->review->sections()->get();

    $summariesString = $sections->map(function ($section) {
        return "[{$section->type->value}] Score: {$section->score}/100\n{$section->summary}";
    })->implode("\n\n");

    $agent = new EditorialSummaryAgent(
        book: $this->book,
        sectionSummaries: $summariesString,
    );

    $response = $agent->prompt('Generate the executive summary.');
    $result = $response->toArray();

    $this->review->update([
        'overall_score' => $result['overall_score'] ?? null,
        'executive_summary' => $result['executive_summary'] ?? null,
        'top_strengths' => $result['top_strengths'] ?? [],
        'top_improvements' => $result['top_improvements'] ?? [],
        'is_pre_editorial' => $result['is_pre_editorial'] ?? false,
        'status' => 'completed',
        'completed_at' => now(),
    ]);
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter="stores is_pre_editorial"`
Expected: PASS

- [ ] **Step 8: Run all editorial review tests to check for regressions**

Run: `php artisan test --compact --filter=EditorialReview`
Expected: All pass (some tests may need `is_pre_editorial` added to fake responses — see Task 8)

- [ ] **Step 9: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Commit**

```bash
git add database/migrations/*add_is_pre_editorial* app/Models/EditorialReview.php app/Jobs/RunEditorialReviewJob.php tests/Feature/RunEditorialReviewJobTest.php
git commit -m "feat: add is_pre_editorial flag for manuscripts below editorial-review level"
```

---

### Task 8: Update existing test fakes to include `is_pre_editorial`

**Files:**
- Modify: `tests/Feature/RunEditorialReviewJobTest.php` (update `fakeAllEditorialAgents` and any other fakes)
- Modify: `tests/Feature/EditorialReviewControllerTest.php` (if it has summary agent fakes)
- Modify: `tests/Feature/EditorialChatAgentTest.php` (if it has summary agent fakes)

- [ ] **Step 1: Update `fakeAllEditorialAgents()` in `RunEditorialReviewJobTest.php`**

Find the `EditorialSummaryAgent::fake(...)` call inside `fakeAllEditorialAgents()` and add `'is_pre_editorial' => false` to its return array. The exact location will be in the helper function — look for the existing fake that returns `overall_score`, `executive_summary`, `top_strengths`, `top_improvements`.

- [ ] **Step 2: Check other test files for summary agent fakes**

Run: `grep -n 'EditorialSummaryAgent::fake' tests/Feature/*.php`

For each match, add `'is_pre_editorial' => false` to the fake return array.

- [ ] **Step 3: Run all editorial tests**

Run: `php artisan test --compact --filter=Editorial`
Expected: All pass

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/RunEditorialReviewJobTest.php tests/Feature/EditorialReviewControllerTest.php tests/Feature/EditorialChatAgentTest.php
git commit -m "test: update editorial test fakes for is_pre_editorial and flexible schema"
```

---

### Task 9: Update TypeScript types and frontend for pre-editorial state

**Files:**
- Modify: `resources/js/types/models.ts:51-71` (add `is_pre_editorial` to EditorialReview type)
- Modify: `resources/js/components/editorial-review/EditorialReviewReport.tsx` (handle pre-editorial state, flexible strengths)
- Modify: `resources/js/i18n/en/editorial-review.json` (add pre-editorial translations)
- Modify: `resources/js/i18n/de/editorial-review.json` (add pre-editorial translations)
- Modify: `resources/js/i18n/es/editorial-review.json` (add pre-editorial translations)

- [ ] **Step 1: Add `is_pre_editorial` to TypeScript type**

In `resources/js/types/models.ts`, add the field to the `EditorialReview` type after `top_improvements`:

```typescript
export type EditorialReview = {
    id: number;
    book_id: number;
    status: EditorialReviewStatus;
    progress: {
        phase: string;
        current_chapter?: number;
        total_chapters?: number;
        current_section?: string;
    } | null;
    error_message: string | null;
    overall_score: number | null;
    executive_summary: string | null;
    top_strengths: string[] | null;
    top_improvements: string[] | null;
    is_pre_editorial: boolean;
    resolved_findings: string[] | null;
    started_at: string | null;
    completed_at: string | null;
    sections: EditorialReviewSection[];
    chapter_notes: EditorialReviewChapterNote[];
};
```

- [ ] **Step 2: Add translation keys**

In `resources/js/i18n/en/editorial-review.json`, add:

```json
"preEditorial.heading": "Pre-Editorial Assessment",
"preEditorial.description": "This manuscript needs foundational work before a full editorial review would be useful. Focus on the areas below first, then run a new review.",
```

In `resources/js/i18n/de/editorial-review.json`, add:

```json
"preEditorial.heading": "Vorab-Einschaetzung",
"preEditorial.description": "Dieses Manuskript benoetigt grundlegende Ueberarbeitung, bevor ein vollstaendiges Lektorat sinnvoll waere. Konzentriere dich zuerst auf die folgenden Bereiche und starte dann eine neue Bewertung.",
```

In `resources/js/i18n/es/editorial-review.json`, add:

```json
"preEditorial.heading": "Evaluacion Preliminar",
"preEditorial.description": "Este manuscrito necesita trabajo fundamental antes de que una revision editorial completa sea util. Concentrate primero en las areas siguientes y luego ejecuta una nueva revision.",
```

- [ ] **Step 3: Update `ScoreDisplay` thresholds**

The current thresholds in `EditorialReviewReport.tsx` are `>= 70` for "Good" and `>= 50` for "Fair". With the new scoring scale (most manuscripts 60-80), adjust to:

```tsx
function ScoreDisplay({
    score,
    qualityLabel,
}: {
    score: number;
    qualityLabel: { good: string; fair: string; needsWork: string };
}) {
    return (
        <div className="flex flex-col items-center gap-1 rounded-lg bg-neutral-bg px-5 py-3">
            <span className="font-serif text-[32px] leading-[1] font-semibold text-ink">
                {score}
            </span>
            <span className="text-[11px] font-medium text-ink-faint">
                {score >= 76
                    ? qualityLabel.good
                    : score >= 60
                      ? qualityLabel.fair
                      : qualityLabel.needsWork}
            </span>
        </div>
    );
}
```

- [ ] **Step 4: Handle pre-editorial state in `EditorialReviewReport`**

In the `EditorialReviewReport` component, add a pre-editorial check before the normal report rendering. Find the return statement and wrap the section rendering in a conditional:

Replace the section after the `StrengthsAndImprovements` block and the `ChapterProgressStrip` and section rendering with:

```tsx
{!review.is_pre_editorial && (
    <>
        <ChapterProgressStrip
            chapters={chapters}
            sections={review.sections}
            resolvedSet={resolvedSet}
            bookId={review.book_id}
        />

        {/* ... existing review selector and section rendering ... */}
    </>
)}

{review.is_pre_editorial && (
    <Card className="flex flex-col gap-4 p-6">
        <SectionLabel>{t('preEditorial.heading')}</SectionLabel>
        <p className="text-[13px] leading-relaxed text-ink-muted">
            {t('preEditorial.description')}
        </p>
        {review.top_improvements && review.top_improvements.length > 0 && (
            <div className="flex flex-col gap-2">
                {review.top_improvements.map((imp, i) => (
                    <div key={i} className="flex items-start gap-2">
                        <span className="mt-[6px] size-2 shrink-0 rounded-full bg-accent" />
                        <span className="text-[13px] leading-relaxed text-ink-muted">
                            {imp}
                        </span>
                    </div>
                ))}
            </div>
        )}
        <div className="pt-2">
            <Button
                variant="primary"
                size="sm"
                onClick={() => setShowConfirm(true)}
                disabled={starting}
            >
                {t('report.startNew')}
            </Button>
        </div>
    </Card>
)}
```

- [ ] **Step 5: Handle flexible strengths (0 strengths possible)**

The current code at line 167-175 already has a guard `review.top_strengths.length > 0`, but the `StrengthsAndImprovements` component shows both columns. Update the guard to handle 0 strengths:

```tsx
{review.top_strengths &&
    review.top_improvements &&
    (review.top_strengths.length > 0 ||
        review.top_improvements.length > 0) &&
    !review.is_pre_editorial && (
        <StrengthsAndImprovements
            strengths={review.top_strengths}
            improvements={review.top_improvements}
        />
    )}
```

Update `StrengthsAndImprovements` to handle empty strengths:

```tsx
function StrengthsAndImprovements({
    strengths,
    improvements,
}: {
    strengths: string[];
    improvements: string[];
}) {
    const { t } = useTranslation('editorial-review');

    return (
        <div className="flex gap-8 border-t border-border-subtle pt-6">
            {strengths.length > 0 && (
                <div className="flex flex-1 flex-col gap-2">
                    <SectionLabel>{t('report.strengths')}</SectionLabel>
                    <div className="flex flex-col gap-2">
                        {strengths.map((s, i) => (
                            <div key={i} className="flex items-start gap-2">
                                <span className="mt-[6px] size-2 shrink-0 rounded-full bg-status-final" />
                                <span className="text-[13px] leading-relaxed text-ink-muted">
                                    {s}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="flex flex-1 flex-col gap-2">
                <SectionLabel>{t('report.improvements')}</SectionLabel>
                <div className="flex flex-col gap-2">
                    {improvements.map((imp, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <span className="mt-[6px] size-2 shrink-0 rounded-full bg-accent" />
                            <span className="text-[13px] leading-relaxed text-ink-muted">
                                {imp}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 6: Build frontend**

Run: `npm run build`

- [ ] **Step 7: Commit**

```bash
git add resources/js/types/models.ts resources/js/components/editorial-review/EditorialReviewReport.tsx resources/js/i18n/en/editorial-review.json resources/js/i18n/de/editorial-review.json resources/js/i18n/es/editorial-review.json
git commit -m "feat: frontend support for pre-editorial state and flexible strengths"
```

---

### Task 10: Run full test suite and verify

**Files:** None (verification only)

- [ ] **Step 1: Run all editorial review tests**

Run: `php artisan test --compact --filter=Editorial`
Expected: All pass

- [ ] **Step 2: Run the persona tests**

Run: `php artisan test --compact --filter=EditorialPersonaTest`
Expected: All pass

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: All pass

- [ ] **Step 4: Run Pint on all modified files**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No changes needed

- [ ] **Step 5: Build frontend successfully**

Run: `npm run build`
Expected: No errors

- [ ] **Step 6: Final commit if any Pint/lint fixes**

Only if needed:
```bash
git add -A
git commit -m "chore: lint and formatting fixes"
```

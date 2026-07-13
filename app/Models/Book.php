<?php

namespace App\Models;

use App\Enums\Genre;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Book extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'writing_style' => 'array',
            'writing_style_prompt_dismissed' => 'boolean',
            'prose_pass_rules' => 'array',
            'daily_word_count_goal' => 'integer',
            'target_word_count' => 'integer',
            'milestone_reached_at' => 'datetime',
            'milestone_dismissed' => 'boolean',
            'ai_input_tokens' => 'integer',
            'ai_output_tokens' => 'integer',
            'ai_cost_microdollars' => 'integer',
            'ai_request_count' => 'integer',
            'ai_usage_reset_at' => 'datetime',
            'genre' => Genre::class,
            'secondary_genres' => 'array',
            'export_drop_caps' => 'boolean',
            'custom_dictionary' => 'array',
            'style_ignored_words' => 'array',
            'cover_settings' => 'array',
            'proofreading_config' => 'array',
            'export_settings' => 'array',
        ];
    }

    public function recordAiUsage(int $inputTokens, int $outputTokens, int $costMicrodollars, ?string $feature = null, ?string $model = null): void
    {
        DB::transaction(function () use ($inputTokens, $outputTokens, $costMicrodollars, $feature, $model) {
            static::query()->where('id', $this->id)->update([
                'ai_input_tokens' => DB::raw("ai_input_tokens + {$inputTokens}"),
                'ai_output_tokens' => DB::raw("ai_output_tokens + {$outputTokens}"),
                'ai_cost_microdollars' => DB::raw("ai_cost_microdollars + {$costMicrodollars}"),
                'ai_request_count' => DB::raw('ai_request_count + 1'),
            ]);

            if ($feature) {
                AiUsageLog::create([
                    'book_id' => $this->id,
                    'feature' => $feature,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cost_microdollars' => $costMicrodollars,
                    'model' => $model,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function resetAiUsage(): void
    {
        $this->update([
            'ai_input_tokens' => 0,
            'ai_output_tokens' => 0,
            'ai_cost_microdollars' => 0,
            'ai_request_count' => 0,
            'ai_usage_reset_at' => now(),
        ]);
    }

    public function getAiCostDisplayAttribute(): string
    {
        return '$'.number_format($this->ai_cost_microdollars / 1_000_000, 4);
    }

    public function getAiAvgCostDisplayAttribute(): string
    {
        if ($this->ai_request_count === 0) {
            return '$0.0000';
        }

        return '$'.number_format($this->ai_cost_microdollars / $this->ai_request_count / 1_000_000, 4);
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public static function defaultProsePassRules(): array
    {
        return [
            ['key' => 'show_dont_tell', 'label' => 'Show, don\'t tell', 'description' => 'Convert telling statements to showing through action and detail.', 'enabled' => true],
            ['key' => 'dialogue_tags', 'label' => 'Dialogue tag cleanup', 'description' => 'Replace adverb-heavy dialogue tags with action beats.', 'enabled' => true],
            ['key' => 'filter_words', 'label' => 'Filter word removal', 'description' => 'Remove unnecessary filter words (felt, saw, heard, noticed).', 'enabled' => true],
            ['key' => 'passive_voice', 'label' => 'Passive voice reduction', 'description' => 'Convert passive constructions to active voice where appropriate.', 'enabled' => true],
            ['key' => 'sentence_variety', 'label' => 'Sentence variety', 'description' => 'Vary sentence length and structure for better rhythm.', 'enabled' => true],
            ['key' => 'shorten_long_sentences', 'label' => 'Shorten overlong sentences', 'description' => "Split a sentence only when it overloads the reader's working memory (~4 chunks): wide subject-verb gaps, more than two levels of clause nesting, or chained items beyond 3–4. Long, fluent sentences with locally connected clauses stay.", 'enabled' => true],
            ['key' => 'tightening', 'label' => 'Prose tightening', 'description' => 'Remove redundancies and tighten wordy phrases.', 'enabled' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $style
     */
    public static function formatWritingStyle(array $style): string
    {
        return collect($style)
            ->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', $key)).': '.(is_array($value) ? implode(', ', $value) : $value))
            ->implode("\n");
    }

    public function getWritingStyleDisplayAttribute(): string
    {
        if ($this->writing_style_text) {
            return $this->writing_style_text;
        }

        if (! $this->writing_style) {
            return '';
        }

        return self::formatWritingStyle($this->writing_style);
    }

    /**
     * The book's prose pass rules. Saved configurations are merged with current defaults
     * so newly added rules appear automatically without requiring users to re-save.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public function prosePassRules(): array
    {
        $defaults = self::defaultProsePassRules();
        $saved = $this->prose_pass_rules;

        if (! is_array($saved) || empty($saved)) {
            return $defaults;
        }

        $savedKeys = collect($saved)->pluck('key')->all();
        $missing = collect($defaults)
            ->reject(fn ($rule) => in_array($rule['key'], $savedKeys, true))
            ->values()
            ->all();

        return [...$saved, ...$missing];
    }

    /**
     * Rules from prosePassRules() that make sense to apply during fresh generation
     * (Continue Writing), not just revision. Mechanical/structural rules transfer cleanly;
     * corrective ones (show_dont_tell, dialogue_tags) are left to the revision pass.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public function generationApplicableProsePassRules(): array
    {
        $applicable = ['shorten_long_sentences', 'sentence_variety', 'tightening', 'passive_voice', 'filter_words'];

        return collect($this->prosePassRules())
            ->filter(fn ($rule) => in_array($rule['key'], $applicable, true))
            ->values()
            ->all();
    }

    /**
     * @return array{spelling_enabled: bool, style_checks: array<string, bool>}
     */
    public static function defaultProofreadingConfig(): array
    {
        return [
            'spelling_enabled' => true,
            'style_checks' => [
                'filler' => true,
                'weakVerb' => true,
                'filterWord' => true,
                'cliche' => true,
                'pattern' => true,
                'repetition' => true,
                'rhythm' => true,
            ],
        ];
    }

    /**
     * Stored configs may predate the style engine (write-good era) or miss
     * categories added since they were saved — normalize on read.
     *
     * @return array{spelling_enabled: bool, style_checks: array<string, bool>}
     */
    public function proofreadingConfig(): array
    {
        $saved = $this->proofreading_config;
        $config = self::defaultProofreadingConfig();

        if (! is_array($saved) || empty($saved)) {
            return $config;
        }

        $config['spelling_enabled'] = (bool) ($saved['spelling_enabled'] ?? true);

        if (isset($saved['style_checks']) && is_array($saved['style_checks'])) {
            foreach ($config['style_checks'] as $category => $default) {
                $config['style_checks'][$category] = (bool) ($saved['style_checks'][$category] ?? $default);
            }

            return $config;
        }

        // Legacy write-good config: the only preserved intent is a full
        // grammar opt-out; individual check keys have no 1:1 equivalents.
        if (($saved['grammar_enabled'] ?? true) === false) {
            $config['style_checks'] = array_map(fn () => false, $config['style_checks']);
        }

        return $config;
    }

    /**
     * Return a genre context snippet ready to append to agent instructions.
     */
    public function genreSnippet(): string
    {
        if (! $this->genre) {
            return '';
        }

        $primary = $this->genre->label();
        $snippet = "Genre context: This is a {$primary} manuscript.";

        if (! empty($this->secondary_genres)) {
            $labels = collect($this->secondary_genres)
                ->map(fn (string $v) => Genre::tryFrom($v)?->label() ?? $v)
                ->implode(', ');
            $snippet .= " It also draws from: {$labels}.";
        }

        return $snippet;
    }

    /**
     * Below this many words of prose, style extraction produces noise rather
     * than signal — sampling refuses and callers must treat the style as
     * not-yet-derivable.
     */
    public const STYLE_SAMPLE_MIN_WORDS = 300;

    public const STYLE_SAMPLE_MAX_WORDS = 5000;

    public const STYLE_SAMPLE_CHAPTERS = 3;

    /**
     * Build the prose sample used for writing-style extraction: the first
     * chapters (by reader order) that actually contain prose, tags stripped
     * but paragraph breaks preserved, capped in length. Returns null when the
     * book holds too little prose for a meaningful analysis.
     */
    public function writingStyleSample(): ?string
    {
        $sampleTexts = $this->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->get()
            ->map(fn (Chapter $chapter) => $chapter->currentVersion?->content)
            ->filter()
            ->take(self::STYLE_SAMPLE_CHAPTERS)
            ->map(function (string $content) {
                $withBreaks = preg_replace('#</(p|h[1-6]|li|blockquote|div)>#i', "$0\n\n", $content);

                return trim(strip_tags($withBreaks));
            })
            ->filter()
            ->values()
            ->all();

        if (empty($sampleTexts)) {
            return null;
        }

        $combined = implode("\n\n---\n\n", $sampleTexts);
        $words = preg_split('/\s+/', trim($combined));

        if (count($words) < self::STYLE_SAMPLE_MIN_WORDS) {
            return null;
        }

        if (count($words) > self::STYLE_SAMPLE_MAX_WORDS) {
            // Slice by token (word + following whitespace) so paragraph breaks
            // survive the cap — the analysis reads them as structure.
            $tokens = preg_split('/(\s+)/', trim($combined), -1, PREG_SPLIT_DELIM_CAPTURE);
            $combined = implode('', array_slice($tokens, 0, self::STYLE_SAMPLE_MAX_WORDS * 2 - 1));
        }

        return $combined;
    }

    /**
     * Whether the editor should offer to derive a writing style before running
     * a prose-generating AI feature: no style yet, the offer was not dismissed,
     * and there is enough prose to analyze. Uses the cheap word_count sum as a
     * proxy for the authoritative writingStyleSample() check.
     */
    public function writingStylePromptable(): bool
    {
        return $this->writing_style_display === ''
            && ! $this->writing_style_prompt_dismissed
            && $this->chapters()->sum('word_count') >= self::STYLE_SAMPLE_MIN_WORDS;
    }

    /**
     * Return a writing-style snippet ready to append to agent instructions.
     */
    public function writingStyleSnippet(string $label = 'Writing style preferences'): string
    {
        $display = $this->writing_style_display;

        return $display ? "\n\n{$label}:\n".$display : '';
    }

    /**
     * @return HasMany<Storyline, $this>
     */
    public function storylines(): HasMany
    {
        return $this->hasMany(Storyline::class);
    }

    /**
     * @return HasMany<Act, $this>
     */
    public function acts(): HasMany
    {
        return $this->hasMany(Act::class);
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * @return HasMany<PlotPoint, $this>
     */
    public function plotPoints(): HasMany
    {
        return $this->hasMany(PlotPoint::class);
    }

    /**
     * @return HasMany<PlotPointConnection, $this>
     */
    public function plotPointConnections(): HasMany
    {
        return $this->hasMany(PlotPointConnection::class);
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return HasMany<WritingSession, $this>
     */
    public function writingSessions(): HasMany
    {
        return $this->hasMany(WritingSession::class);
    }

    /**
     * @return HasMany<WikiEntry, $this>
     */
    public function wikiEntries(): HasMany
    {
        return $this->hasMany(WikiEntry::class);
    }

    /**
     * @return HasMany<AiUsageLog, $this>
     */
    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    /**
     * @return HasMany<EditorialReview, $this>
     */
    public function editorialReviews(): HasMany
    {
        return $this->hasMany(EditorialReview::class);
    }
}

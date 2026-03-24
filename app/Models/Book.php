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
            'story_bible' => 'array',
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
            'nanowrimo_year' => 'integer',
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
        // Check global setting first, fall back to book's own column
        $globalText = AppSetting::get('writing_style_text');
        if ($globalText) {
            return $globalText;
        }

        if ($this->writing_style_text) {
            return $this->writing_style_text;
        }

        if (! $this->writing_style) {
            return '';
        }

        return self::formatWritingStyle($this->writing_style);
    }

    /**
     * Get global prose pass rules from AppSetting, or fall back to book/default.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public static function globalProsePassRules(): array
    {
        $json = AppSetting::get('prose_pass_rules');

        if ($json) {
            $decoded = is_string($json) ? json_decode($json, true) : $json;

            if (is_array($decoded) && ! empty($decoded)) {
                return $decoded;
            }
        }

        return self::defaultProsePassRules();
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
     * @return HasMany<AiPreparation, $this>
     */
    public function aiPreparations(): HasMany
    {
        return $this->hasMany(AiPreparation::class);
    }

    /**
     * @return HasMany<WritingSession, $this>
     */
    public function writingSessions(): HasMany
    {
        return $this->hasMany(WritingSession::class);
    }

    /**
     * @return HasMany<HealthSnapshot, $this>
     */
    public function healthSnapshots(): HasMany
    {
        return $this->hasMany(HealthSnapshot::class);
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

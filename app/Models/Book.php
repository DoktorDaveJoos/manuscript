<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'ai_provider' => AiProvider::class,
            'ai_enabled' => 'boolean',
            'writing_style' => 'array',
            'story_bible' => 'array',
            'prose_pass_rules' => 'array',
            'daily_word_count_goal' => 'integer',
        ];
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
        if ($this->writing_style_text) {
            return $this->writing_style_text;
        }

        if (! $this->writing_style) {
            return '';
        }

        return self::formatWritingStyle($this->writing_style);
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
}

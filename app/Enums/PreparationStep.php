<?php

namespace App\Enums;

/**
 * A user-selectable step in the AI preparation pipeline. Each step maps to one
 * or more granular phases tracked on the AiPreparation record.
 */
enum PreparationStep: string
{
    case SemanticIndex = 'semantic_index';
    case WritingStyle = 'writing_style';
    case ChapterAnalysis = 'chapter_analysis';
    case Wiki = 'wiki';
    case StoryBible = 'story_bible';
    case Health = 'health';

    /**
     * The granular phase keys this step is responsible for, as tracked in
     * AiPreparation::completed_phases.
     *
     * @return list<string>
     */
    public function phases(): array
    {
        return match ($this) {
            self::SemanticIndex => ['chunking', 'embedding'],
            self::WritingStyle => ['writing_style'],
            self::ChapterAnalysis => ['chapter_analysis'],
            self::Wiki => ['entity_extraction'],
            self::StoryBible => ['story_bible'],
            self::Health => ['health_analysis'],
        };
    }

    /**
     * Steps that must also be selected for this step to run.
     *
     * @return list<PreparationStep>
     */
    public function requires(): array
    {
        return match ($this) {
            self::StoryBible, self::Health => [self::ChapterAnalysis],
            default => [],
        };
    }

    /**
     * All step string values in pipeline order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $step) => $step->value, self::cases());
    }

    /**
     * Total number of granular phases across the given selected step values.
     *
     * @param  list<string>  $steps
     */
    public static function totalPhasesFor(array $steps): int
    {
        return collect($steps)
            ->map(fn (string $value) => self::tryFrom($value))
            ->filter()
            ->sum(fn (self $step) => count($step->phases()));
    }
}

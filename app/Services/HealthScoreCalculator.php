<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Collection;

class HealthScoreCalculator
{
    /** @var Collection<int, Chapter> */
    private Collection $analyzed;

    /**
     * @param  Collection<int, Chapter>  $analyzed  Chapters with hook_score !== null
     */
    public function __construct(Collection $analyzed)
    {
        $this->analyzed = $analyzed;
    }

    /**
     * @return array{scene_purpose: int, pacing: int, tension_dynamics: int, hooks: int, emotional_arc: int, craft: int, composite: int}
     */
    public function calculate(): array
    {
        $scenePurpose = $this->scenePurposeScore();
        $pacing = $this->pacingScore();
        $tensionDynamics = $this->tensionDynamicsScore();
        $hooks = $this->hooksScore();
        $emotionalArc = $this->emotionalArcScore();
        $craft = $this->craftScore();

        $composite = (int) round(
            $scenePurpose * 0.15
            + $pacing * 0.15
            + $tensionDynamics * 0.20
            + $hooks * 0.20
            + $emotionalArc * 0.15
            + $craft * 0.15
        );

        return [
            'scene_purpose' => $scenePurpose,
            'pacing' => $pacing,
            'tension_dynamics' => $tensionDynamics,
            'hooks' => $hooks,
            'emotional_arc' => $emotionalArc,
            'craft' => $craft,
            'composite' => $composite,
        ];
    }

    /**
     * % of analyzed chapters with a clear value turn (not just transition with no shift).
     */
    private function scenePurposeScore(): int
    {
        $purposeful = $this->analyzed->filter(fn ($ch) => $ch->scene_purpose !== null && $ch->value_shift !== null);

        return (int) round(($purposeful->count() / $this->analyzed->count()) * 100);
    }

    /**
     * Measures variety of pacing_feel values. Good manuscripts have variety, not all one speed.
     */
    private function pacingScore(): int
    {
        $withPacing = $this->analyzed->filter(fn ($ch) => $ch->pacing_feel !== null);

        if ($withPacing->count() < 2) {
            return 50;
        }

        $pacingCounts = $withPacing->groupBy('pacing_feel')->map->count();
        $total = $withPacing->count();

        $varietyScore = min(100, (int) round(($pacingCounts->count() / min(5, $total)) * 100));

        $dominantRatio = $pacingCounts->max() / $total;
        $balanceScore = $dominantRatio <= 0.5
            ? 100
            : min(100, max(0, (int) round((1 - $dominantRatio) * 200)));

        return (int) round($varietyScore * 0.5 + $balanceScore * 0.5);
    }

    /**
     * Quality of tension ebb/flow pattern — the PATTERN matters, not the average.
     */
    private function tensionDynamicsScore(): int
    {
        $tensionChapters = $this->analyzed->filter(fn ($ch) => $ch->tension_score !== null)->sortBy('reader_order')->values();

        if ($tensionChapters->count() < 3) {
            return 50;
        }

        $stepChanges = [];
        for ($i = 1; $i < $tensionChapters->count(); $i++) {
            $stepChanges[] = abs($tensionChapters[$i]->tension_score - $tensionChapters[$i - 1]->tension_score);
        }
        $avgStepChange = array_sum($stepChanges) / count($stepChanges);
        $variationScore = min(100, max(0, (int) round(100 - abs($avgStepChange - 3.0) * 25)));

        $lowConflict = $this->analyzed->filter(fn ($ch) => $ch->tension_score !== null && $ch->tension_score <= 3);
        if ($lowConflict->isNotEmpty()) {
            $filledCount = $lowConflict->filter(fn ($ch) => $ch->micro_tension_score !== null && $ch->micro_tension_score >= 5)->count();
            $microFillScore = (int) round(($filledCount / $lowConflict->count()) * 100);
        } else {
            $microFillScore = 100;
        }

        return (int) round($variationScore * 0.6 + $microFillScore * 0.4);
    }

    /**
     * Combined entry + exit hook scoring.
     */
    private function hooksScore(): int
    {
        $avgExit = $this->analyzed->avg(fn ($ch) => $ch->exit_hook_score ?? $ch->hook_score);
        $withEntry = $this->analyzed->filter(fn ($ch) => $ch->entry_hook_score !== null);
        $avgEntry = $withEntry->isNotEmpty() ? $withEntry->avg('entry_hook_score') : $avgExit;

        return min(100, max(0, (int) round(($avgExit * 0.6 + $avgEntry * 0.4) * 10)));
    }

    /**
     * Quality of emotional progression.
     */
    private function emotionalArcScore(): int
    {
        $withShift = $this->analyzed->filter(fn ($ch) => $ch->emotional_shift_magnitude !== null);

        if ($withShift->count() < 2) {
            return 50;
        }

        $avgShift = $withShift->avg('emotional_shift_magnitude');
        $shiftQuality = min(100, max(0, (int) round(100 - abs($avgShift - 5) * 20)));

        $bigBeats = $withShift->filter(fn ($ch) => $ch->emotional_shift_magnitude >= 7)->count();
        $beatRatio = $bigBeats / $withShift->count();
        if ($beatRatio >= 0.2 && $beatRatio <= 0.4) {
            $beatScore = 100;
        } elseif ($beatRatio < 0.2) {
            $beatScore = max(0, (int) round($beatRatio / 0.2 * 100));
        } else {
            $beatScore = max(0, (int) round((1 - ($beatRatio - 0.4) / 0.6) * 100));
        }

        return (int) round($shiftQuality * 0.5 + $beatScore * 0.5);
    }

    /**
     * Composite of sensory grounding + information delivery quality.
     */
    private function craftScore(): int
    {
        $withSensory = $this->analyzed->filter(fn ($ch) => $ch->sensory_grounding !== null);
        $sensoryScore = $withSensory->isNotEmpty()
            ? min(100, (int) round($withSensory->avg('sensory_grounding') / 3 * 100))
            : 50;

        $withDelivery = $this->analyzed->filter(fn ($ch) => $ch->information_delivery !== null);
        if ($withDelivery->isNotEmpty()) {
            $organicCount = $withDelivery->filter(fn ($ch) => in_array($ch->information_delivery, ['organic', 'mostly_organic']))->count();
            $deliveryScore = (int) round(($organicCount / $withDelivery->count()) * 100);
        } else {
            $deliveryScore = 50;
        }

        return (int) round($sensoryScore * 0.5 + $deliveryScore * 0.5);
    }
}

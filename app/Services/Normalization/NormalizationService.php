<?php

namespace App\Services\Normalization;

use App\Services\Normalization\Rules\DashRule;
use App\Services\Normalization\Rules\DialogueRule;
use App\Services\Normalization\Rules\EllipsisRule;
use App\Services\Normalization\Rules\ParagraphRule;
use App\Services\Normalization\Rules\SmartQuoteRule;
use App\Services\Normalization\Rules\WhitespaceRule;

class NormalizationService
{
    /** @var NormalizationRule[] */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new WhitespaceRule,
            new ParagraphRule,
            new EllipsisRule,
            new DashRule,
            new SmartQuoteRule,
            new DialogueRule,
        ];
    }

    /**
     * @return array{content: string, changes: array<int, array{rule: string, count: int}>, total_changes: int}
     */
    public function normalize(string $html, string $language): array
    {
        $content = $html;
        $changes = [];
        $totalChanges = 0;

        foreach ($this->rules as $rule) {
            $result = $rule->apply($content, $language);
            $content = $result['content'];

            if ($result['changes'] > 0) {
                $changes[] = [
                    'rule' => $rule->name(),
                    'count' => $result['changes'],
                ];
                $totalChanges += $result['changes'];
            }
        }

        return [
            'content' => $content,
            'changes' => $changes,
            'total_changes' => $totalChanges,
        ];
    }
}

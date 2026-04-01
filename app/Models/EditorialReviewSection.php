<?php

namespace App\Models;

use App\Enums\EditorialSectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialReviewSection extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EditorialSectionType::class,
            'score' => 'integer',
            'findings' => 'array',
            'recommendations' => 'array',
        ];
    }

    /**
     * Generate a deterministic key for a finding.
     */
    public static function findingKey(string $sectionType, string $description): string
    {
        return hash('xxh128', $sectionType.':'.mb_substr($description, 0, 100));
    }

    /**
     * Ensure all findings have a key field, adding keys where missing.
     */
    public function ensureFindingKeys(): void
    {
        $findings = $this->findings;

        if (empty($findings)) {
            return;
        }

        $changed = false;

        foreach ($findings as &$finding) {
            if (empty($finding['key'])) {
                $finding['key'] = self::findingKey($this->type->value, $finding['description'] ?? '');
                $changed = true;
            }
        }

        if ($changed) {
            $this->update(['findings' => $findings]);
        }
    }

    /**
     * @return BelongsTo<EditorialReview, $this>
     */
    public function editorialReview(): BelongsTo
    {
        return $this->belongsTo(EditorialReview::class);
    }
}

<?php

namespace App\Models\Concerns;

trait HasDualDescription
{
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
}

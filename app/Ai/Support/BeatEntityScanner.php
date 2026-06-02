<?php

namespace App\Ai\Support;

/**
 * Scans beat-description text for references to known book entities by name.
 *
 * Pure, stateless. Used by ProposeChapterPlan / ProposeBatch validation to
 * detect when a chapter's beats reference characters or wiki entries that
 * the agent's proposal failed to declare.
 */
class BeatEntityScanner
{
    private const MIN_NAME_LENGTH = 3;

    /**
     * @param  list<string>  $beatDescriptions  text of each beat, in order
     * @param  list<array{id: int, name: string}>  $entities  candidate entities
     * @return list<array{id: int, name: string, beats: list<int>}> matches with beat indices
     */
    public function findReferenced(array $beatDescriptions, array $entities): array
    {
        if ($beatDescriptions === [] || $entities === []) {
            return [];
        }

        $matches = [];

        foreach ($entities as $entity) {
            $name = trim((string) ($entity['name'] ?? ''));

            if (mb_strlen($name) < self::MIN_NAME_LENGTH) {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu';
            $hitBeats = [];

            foreach ($beatDescriptions as $index => $description) {
                if (preg_match($pattern, (string) $description) === 1) {
                    $hitBeats[] = $index;
                }
            }

            if ($hitBeats !== []) {
                $matches[] = [
                    'id' => (int) $entity['id'],
                    'name' => $name,
                    'beats' => $hitBeats,
                ];
            }
        }

        return $matches;
    }
}

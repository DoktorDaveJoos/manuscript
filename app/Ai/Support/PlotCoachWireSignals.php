<?php

namespace App\Ai\Support;

/**
 * Single source of truth for the wire signals exchanged between the plot
 * coach UI (approval/undo buttons) and the controller, and for the
 * `[system: ...]` prefix used for server-applied turn notes. Three sites
 * read these — `PlotCoachController` (intercepts before the LLM sees the
 * turn, sanitizes the rendered chat history) and `PlotCoachAgent`
 * (classifies trivial turns for cheap-model routing). Drift between them
 * silently breaks approvals — keep the patterns here.
 */
final class PlotCoachWireSignals
{
    public const SYSTEM_PREFIX = '[system:';

    public const PATTERN_APPROVE = '/^APPROVE:batch:([0-9a-f-]{36})$/i';

    public const PATTERN_CANCEL = '/^CANCEL:batch:([0-9a-f-]{36})$/i';

    public const PATTERN_UNDO_PROPOSAL = '/^UNDO:proposal:([0-9a-f-]{36})$/i';

    public const PATTERN_UNDO_LAST = '/^UNDO:last$/i';

    /**
     * Combined matcher used to detect "is this any wire signal?" without
     * caring which one. Mirrors the four PATTERN_* constants above.
     */
    public const PATTERN_ANY = '/^(APPROVE:batch:[0-9a-f-]{36}|CANCEL:batch:[0-9a-f-]{36}|UNDO:proposal:[0-9a-f-]{36}|UNDO:last)$/i';

    /**
     * Strip internal scaffolding from a user turn before exposing it to a
     * human (chat rehydrate, transcript export) or to a digest:
     *  - leading `[system: ...]` notes the controller prepended (approval
     *    outcomes, board-change digests, archive summaries). Notes may stack,
     *    and their bodies may legitimately contain brackets (error messages,
     *    markdown), so the closer is found by balance, not by first-`]`.
     *  - bare wire signals (`APPROVE:batch:<uuid>` etc.) from approval-card
     *    buttons that older conversations stored verbatim.
     *
     * Returns an empty string for turns that are nothing but scaffolding —
     * callers are expected to drop those from rendered output.
     */
    public static function stripScaffolding(string $content): string
    {
        $content = ltrim($content);

        while (str_starts_with($content, self::SYSTEM_PREFIX)) {
            $end = self::findBalancedNoteEnd($content);

            if ($end === null) {
                // Unterminated note — the whole turn is scaffolding remnant.
                return '';
            }

            $content = ltrim(substr($content, $end + 1));
        }

        if (preg_match(self::PATTERN_ANY, trim($content))) {
            return '';
        }

        return $content;
    }

    /**
     * Offset of the `]` closing the `[system:` note at position 0, or null
     * when the note never closes. Tracks bracket depth so inner balanced
     * pairs (e.g. "SQLSTATE[23000]") don't end the note early.
     */
    private static function findBalancedNoteEnd(string $content): ?int
    {
        $depth = 0;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            if ($content[$i] === '[') {
                $depth++;
            } elseif ($content[$i] === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }
}

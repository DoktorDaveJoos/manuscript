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
}

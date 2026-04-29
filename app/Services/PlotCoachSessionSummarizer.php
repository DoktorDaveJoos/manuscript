<?php

namespace App\Services;

use App\Ai\Agents\PlotCoachAgent;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use Illuminate\Support\Facades\DB;

/**
 * Build a compact, deterministic archive summary for a Plot Coach session.
 *
 * Two outputs, one per downstream consumer:
 *   - {@see buildArchiveSummary()} — YAML-ish text stored on `archive_summary`
 *     and injected as a `[system: ...]` prefix into the first user turn of a
 *     successor session so the new agent still has context.
 *   - {@see buildTranscriptMarkdown()} — full markdown transcript, used by the
 *     export endpoint. Includes conversation turns + decisions + entities.
 */
class PlotCoachSessionSummarizer
{
    /** @var array<int, array<string, int>> */
    private array $entityCountCache = [];

    /**
     * Hard cap on the transcript digest included in the archive summary.
     * ~4 000 chars ≈ ~1 000 tokens — plenty of context without burning budget.
     */
    private const TRANSCRIPT_DIGEST_BUDGET = 4000;

    /**
     * Number of earliest and latest user/assistant turns to include when the
     * full transcript exceeds the digest budget. We keep the opening (premise,
     * character intake) and the tail (most recent decisions) and drop the
     * middle — that's where conversations typically meander most.
     */
    private const DIGEST_HEAD_TURNS = 4;

    private const DIGEST_TAIL_TURNS = 4;

    /**
     * Number of most-recent conversation messages (user + assistant) the agent
     * replays verbatim each turn. Must stay in sync with
     * {@see PlotCoachAgent::maxConversationMessages()}.
     */
    public const ROLLING_DIGEST_TAIL_MESSAGES = 40;

    /** Chars per message when rendering the rolling in-session digest. */
    private const ROLLING_DIGEST_BUDGET = 4000;

    public function buildArchiveSummary(PlotCoachSession $session): string
    {
        $decisions = is_array($session->decisions) ? $session->decisions : [];

        $lines = [];
        $lines[] = 'Plot Coach archive summary';
        $lines[] = 'stage: '.$session->stage->value;

        if ($session->coaching_mode) {
            $lines[] = 'coaching_mode: '.$session->coaching_mode->value;
        }

        if ($session->book) {
            $book = $session->book;
            $lines[] = 'book: '.$this->inline((string) $book->title);
            if ($book->author) {
                $lines[] = 'author: '.$this->inline((string) $book->author);
            }
            if ($book->genre) {
                $lines[] = 'genre: '.$book->genre->label();
            }
            if ($book->target_word_count) {
                $lines[] = 'target_word_count: '.$book->target_word_count;
            }
            if ($book->premise) {
                $lines[] = 'premise: '.$this->inline((string) $book->premise);
            }
        }

        foreach (['genre', 'target_length', 'structure_template', 'premise', 'protagonist', 'conflict'] as $key) {
            $value = $decisions[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $lines[] = "decision.{$key}: ".$this->inline($value);
            } elseif (is_array($value) && ! empty($value)) {
                $lines[] = "decision.{$key}: ".$this->inline(json_encode($value, JSON_UNESCAPED_SLASHES));
            }
        }

        $openThreads = $decisions['open_threads'] ?? [];

        if (is_array($openThreads) && $openThreads !== []) {
            $lines[] = 'open_threads:';
            foreach (array_slice($openThreads, 0, 10) as $thread) {
                if (is_string($thread) && trim($thread) !== '') {
                    $lines[] = '  - '.$this->inline($thread);
                }
            }
        }

        $entityCounts = $this->countEntities($session);

        if ($entityCounts !== []) {
            $parts = [];
            foreach ($entityCounts as $type => $count) {
                $parts[] = "{$type}={$count}";
            }
            $lines[] = 'entities: '.implode(', ', $parts);
        }

        $digest = $this->buildTranscriptDigest($session);

        if ($digest !== '') {
            $lines[] = '';
            $lines[] = 'transcript_digest:';
            $lines[] = $digest;
        }

        return implode("\n", $lines);
    }

    /**
     * Condense the stored conversation into a token-budgeted digest the next
     * agent can read on every turn. Keeps the opening turns (intake / premise
     * framing) and the tail turns (latest decisions) and elides the middle
     * when the conversation is long.
     */
    private function buildTranscriptDigest(PlotCoachSession $session): string
    {
        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $session->agent_conversation_id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => trim((string) $m->content),
            ])
            ->filter(fn ($m) => $m['content'] !== '')
            ->values()
            ->all();

        if ($messages === []) {
            return '';
        }

        $total = count($messages);
        $headCount = self::DIGEST_HEAD_TURNS * 2; // user + assistant pairs
        $tailCount = self::DIGEST_TAIL_TURNS * 2;

        if ($total <= $headCount + $tailCount) {
            $selected = $messages;
            $elided = 0;
        } else {
            $head = array_slice($messages, 0, $headCount);
            $tail = array_slice($messages, -$tailCount);
            $elided = $total - $headCount - $tailCount;
            $selected = [...$head, ['role' => '…', 'content' => "[{$elided} turns elided]"], ...$tail];
        }

        $perMessageBudget = max(200, (int) (self::TRANSCRIPT_DIGEST_BUDGET / max(1, count($selected))));

        $rendered = [];
        foreach ($selected as $message) {
            $speaker = match ($message['role']) {
                'user' => 'Author',
                'assistant' => 'Coach',
                default => $message['role'],
            };
            $rendered[] = "  {$speaker}: ".$this->inline($message['content'], $perMessageBudget);
        }

        return implode("\n", $rendered);
    }

    /**
     * Build an in-session rolling digest of everything older than the agent's
     * verbatim replay window. The agent injects this between the parent
     * handoff and the state block so long sessions "feel continuous" — the
     * coach still remembers the opening premise and mid-session decisions
     * without starting a fresh session.
     */
    public function buildInSessionDigest(PlotCoachSession $session): string
    {
        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $session->agent_conversation_id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => trim((string) $m->content),
            ])
            ->filter(fn ($m) => $m['content'] !== '')
            ->values()
            ->all();

        $total = count($messages);

        if ($total <= self::ROLLING_DIGEST_TAIL_MESSAGES) {
            return '';
        }

        $preTail = array_slice($messages, 0, $total - self::ROLLING_DIGEST_TAIL_MESSAGES);
        $perMessage = max(80, (int) (self::ROLLING_DIGEST_BUDGET / max(1, count($preTail))));

        $rendered = [];
        foreach ($preTail as $message) {
            $speaker = $message['role'] === 'user' ? 'Author' : 'Coach';
            $rendered[] = "  {$speaker}: ".$this->inline($message['content'], $perMessage);
        }

        return implode("\n", $rendered);
    }

    public function buildTranscriptMarkdown(PlotCoachSession $session): string
    {
        $sections = [];

        $sections[] = '# Plot Coach session #'.$session->id;
        $sections[] = 'Book: '.($session->book?->title ?? 'unknown');
        $sections[] = 'Stage: '.$session->stage->value.' · Status: '.$session->status->value;

        if ($session->archived_at) {
            $sections[] = 'Archived: '.$session->archived_at->toIso8601String();
        }

        $sections[] = '';
        $sections[] = '## Decisions';
        $sections[] = '';
        $sections[] = '```';
        $sections[] = $this->buildArchiveSummary($session);
        $sections[] = '```';

        $sections[] = '';
        $sections[] = '## Entities created';
        $sections[] = '';

        $entityLines = $this->describeEntities($session);

        if ($entityLines === []) {
            $sections[] = '_None._';
        } else {
            foreach ($entityLines as $line) {
                $sections[] = '- '.$line;
            }
        }

        $sections[] = '';
        $sections[] = '## Conversation';
        $sections[] = '';

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $session->agent_conversation_id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get(['role', 'content', 'created_at']);

        if ($messages->isEmpty()) {
            $sections[] = '_No messages._';
        } else {
            foreach ($messages as $message) {
                $speaker = $message->role === 'user' ? '**You**' : '**Coach**';
                $sections[] = $speaker;
                $sections[] = '';
                $sections[] = trim((string) $message->content) === ''
                    ? '_(empty)_'
                    : trim((string) $message->content);
                $sections[] = '';
            }
        }

        return implode("\n", $sections)."\n";
    }

    /**
     * Group a flat writes array by `type`, returning `['character' => 2, ...]`.
     *
     * @param  array<int, array{type?: string}>  $writes
     * @return array<string, int>
     */
    public static function countWritesByType(array $writes): array
    {
        $counts = [];

        foreach ($writes as $write) {
            $type = $write['type'] ?? null;
            if (! is_string($type)) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Human-friendly one-liner for a set of writes, e.g. "saved 2 characters,
     * 1 storyline". Used by the controller's approval-outcome prefix.
     *
     * @param  array<int, array{type?: string}>  $writes
     */
    public static function summarizeWrites(array $writes): string
    {
        if (empty($writes)) {
            return 'no items';
        }

        $counts = self::countWritesByType($writes);

        if (empty($counts)) {
            return count($writes).' items';
        }

        $parts = [];
        foreach ($counts as $type => $count) {
            $parts[] = $count.' '.$type.($count === 1 ? '' : 's');
        }

        return 'saved '.implode(', ', $parts);
    }

    /**
     * @return array<string, int>
     */
    private function countEntities(PlotCoachSession $session): array
    {
        if (isset($this->entityCountCache[$session->id])) {
            return $this->entityCountCache[$session->id];
        }

        $counts = [];

        $batches = PlotCoachBatch::query()
            ->where('session_id', $session->id)
            ->whereNull('reverted_at')
            ->get(['payload']);

        foreach ($batches as $batch) {
            $writes = $batch->payload['writes'] ?? [];

            foreach (self::countWritesByType(is_array($writes) ? $writes : []) as $type => $count) {
                $counts[$type] = ($counts[$type] ?? 0) + $count;
            }
        }

        ksort($counts);

        return $this->entityCountCache[$session->id] = $counts;
    }

    /**
     * @return list<string>
     */
    private function describeEntities(PlotCoachSession $session): array
    {
        $counts = $this->countEntities($session);

        return array_map(
            fn (int $count, string $type) => "{$count} {$type}".($count === 1 ? '' : 's'),
            array_values($counts),
            array_keys($counts),
        );
    }

    private function inline(string $value, int $limit = 400): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

        if (mb_strlen($collapsed) <= $limit) {
            return $collapsed;
        }

        return mb_substr($collapsed, 0, $limit - 1).'…';
    }
}

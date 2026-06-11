<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BlurbAgent;
use App\Ai\Support\AiErrorClassifier;
use App\Http\Controllers\Concerns\EnsuresAiConfigured;
use App\Models\AiSetting;
use App\Models\Book;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlurbController extends Controller
{
    use EnsuresAiConfigured;

    /**
     * Stream a back-cover blurb ("Klappentext") generated from the book's plot board.
     * The streamed text fills the Klappentext field in the Publish editor, which then
     * persists it through the regular publish-settings save.
     */
    public function stream(Book $book): StreamedResponse
    {
        $this->ensureAiConfigured();

        $agent = new BlurbAgent($book);
        $streamable = $agent->stream('Write the back-cover blurb for this book.');

        return response()->stream(function () use ($streamable) {
            try {
                foreach ($streamable as $event) {
                    if (! $event instanceof TextDelta || $event->delta === '') {
                        continue;
                    }
                    echo 'data: '.json_encode(['delta' => $event->delta])."\n\n";
                    $this->sseFlush();
                }
            } catch (\Throwable $e) {
                report($e);
                $classified = AiErrorClassifier::classify(
                    $e,
                    AiSetting::activeProvider()?->provider->value,
                );
                echo 'data: '.json_encode([
                    'error' => $classified['message'] ?: __('Blurb generation failed.'),
                    'kind' => $classified['kind'],
                    'provider' => $classified['provider'],
                ])."\n\n";
                $this->sseFlush();
            }

            echo "data: [DONE]\n\n";
            $this->sseFlush();
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sseFlush(): void
    {
        // Under the in-process test server, flushing would push the streamed
        // body past the response capture buffer into the runner's stdout and
        // the browser would receive an empty stream.
        if (app()->runningUnitTests()) {
            return;
        }

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}

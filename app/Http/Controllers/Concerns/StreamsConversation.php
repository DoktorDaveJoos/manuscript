<?php

namespace App\Http\Controllers\Concerns;

use App\Ai\Support\AiErrorClassifier;
use App\Models\AiSetting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait StreamsConversation
{
    protected function resolveConversation(Request $request): string
    {
        $conversationId = $request->input('conversation_id');

        if (! $conversationId) {
            $conversationId = resolve(ConversationStore::class)->storeConversation(
                $request->user()?->id,
                Str::limit($request->input('message'), 100, preserveWords: true),
            );
        }

        return $conversationId;
    }

    /**
     * @param  callable(): StreamedResponse  $callback
     */
    protected function streamChat(callable $callback): JsonResponse|StreamedResponse
    {
        try {
            return $callback();
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $classified = AiErrorClassifier::classify($e, AiSetting::activeProvider()?->provider->value);

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : ($classified['status_code'] ?: 500);

            return response()->json([
                'message' => $classified['message'] ?: __('Chat request failed.'),
                'kind' => $classified['kind'],
                'provider' => $classified['provider'],
            ], $status);
        }
    }

    protected function streamWithConversationId(StreamableAgentResponse $streamable, string $conversationId): StreamedResponse
    {
        return response()->stream(function () use ($streamable, $conversationId) {
            echo 'data: '.json_encode(['conversation_id' => $conversationId])."\n\n";
            $this->sseFlush();

            try {
                foreach ($streamable as $event) {
                    echo 'data: '.((string) $event)."\n\n";
                    $this->sseFlush();
                }
            } catch (\Throwable $e) {
                report($e);

                $classified = AiErrorClassifier::classify($e, AiSetting::activeProvider()?->provider->value);

                echo 'data: '.json_encode([
                    'error' => $classified['message'] ?: 'An unexpected error occurred.',
                    'kind' => $classified['kind'],
                    'provider' => $classified['provider'],
                ])."\n\n";
                $this->sseFlush();
            }

            echo "data: [DONE]\n\n";
            $this->sseFlush();
        }, headers: ['Content-Type' => 'text/event-stream']);
    }

    private function sseFlush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}

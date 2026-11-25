<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chunk;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\EmbeddingsResponse;

class EmbeddingService
{
    private const STORAGE_DIMENSIONS = 1536;

    public function __construct(private AiUsageService $usageService) {}

    /**
     * Generate and store embeddings for a collection of chunks.
     *
     * @param  Collection<int, Chunk>  $chunks
     */
    public function embedChunks(Collection $chunks, Book $book): void
    {
        if ($chunks->isEmpty()) {
            return;
        }

        $config = $this->resolveConfig($book);

        $chunks->chunk(20)->each(function (Collection $batch) use ($config, $book) {
            $texts = $batch->pluck('content')->all();

            try {
                $response = $this->generate($texts, $config);

                foreach ($batch->values() as $index => $chunk) {
                    $vector = $response->embeddings[$index] ?? null;

                    if (! is_array($vector) || count($vector) !== self::STORAGE_DIMENSIONS) {
                        report(new \RuntimeException(sprintf(
                            'Embedding dimension mismatch for provider %s: expected %d, got %d.',
                            $config->provider,
                            self::STORAGE_DIMENSIONS,
                            is_array($vector) ? count($vector) : 0,
                        )));

                        return false;
                    }

                    $chunk->storeEmbedding($vector, $book->id);
                }
            } catch (RequestException|ConnectionException|AiException|QueryException $e) {
                // Bulk indexing is best-effort: a provider rejection (oversized or
                // proxy-mangled payload — e.g. a 431, an exhausted failover, or a
                // network blip) or vector-storage mismatch degrades retrieval quality
                // but must never crash the queue worker driving an editorial review.
                // Report once for visibility and stop — the same systemic fault would
                // reject every remaining batch identically.
                report($e);

                return false;
            }

            $this->recordEmbeddingUsage($book, $response);
        });
    }

    /**
     * Generate an embedding for a single text query.
     *
     * @return array<int, float>
     */
    public function embedQuery(string $text, Book $book): array
    {
        $config = $this->resolveConfig($book);

        $response = $this->generate([$text], $config);

        $this->recordEmbeddingUsage($book, $response);

        return $response->embeddings[0];
    }

    /**
     * @return object{provider: string, model: string|null, dimensions: int|null}
     */
    private function resolveConfig(Book $book): object
    {
        $setting = AiSetting::activeProvider();
        abort_if(! $setting, 422, 'No AI provider configured.');

        $setting->injectConfig();

        // Gemini defaults to 3072-dim embeddings; request MRL-truncated 1536 dims
        // to match the float[1536] chunk_embeddings virtual table schema.
        $dimensions = $setting->embedding_dimensions
            ?? ($setting->provider === AiProvider::Gemini ? self::STORAGE_DIMENSIONS : null);

        return (object) [
            'provider' => $setting->provider->toLab()->value,
            'model' => $setting->embedding_model,
            'dimensions' => $dimensions,
        ];
    }

    /**
     * @param  array<int, string>  $texts
     */
    private function generate(array $texts, object $config): EmbeddingsResponse
    {
        $builder = Embeddings::for($texts);

        if ($config->dimensions) {
            $builder->dimensions($config->dimensions);
        }

        return $builder->generate($config->provider, $config->model);
    }

    private function recordEmbeddingUsage(Book $book, EmbeddingsResponse $response): void
    {
        $model = $response->meta->model ?? 'unknown';
        $cost = $this->usageService->calculateEmbeddingCost($response->tokens, $model);

        $book->recordAiUsage($response->tokens, 0, $cost, 'embedding', $response->meta->model ?? null);
    }
}

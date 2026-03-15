<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chunk;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\EmbeddingsResponse;

class EmbeddingService
{
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

            $response = $this->generate($texts, $config);

            foreach ($batch->values() as $index => $chunk) {
                $chunk->storeEmbedding($response->embeddings[$index], $book->id);
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

        return (object) [
            'provider' => $setting->provider->toLab()->value,
            'model' => $setting->embedding_model,
            'dimensions' => $setting->embedding_dimensions,
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

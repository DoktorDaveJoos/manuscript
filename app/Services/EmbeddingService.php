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

        $chunks->chunk(20)->each(function (Collection $batch) use ($config) {
            $texts = $batch->pluck('content')->all();

            $response = $this->generate($texts, $config);

            foreach ($batch->values() as $index => $chunk) {
                $chunk->storeEmbedding($response->embeddings[$index]);
            }
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

        return $this->generate([$text], $config)->embeddings[0];
    }

    /**
     * @return object{provider: string, model: string|null, dimensions: int|null}
     */
    private function resolveConfig(Book $book): object
    {
        $setting = AiSetting::forProvider($book->ai_provider);
        $setting->injectConfig();

        return (object) [
            'provider' => $book->ai_provider->toLab()->value,
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
}

<?php

namespace App\Http\Controllers;

use App\Enums\AiProvider;
use App\Http\Requests\UpdateAiSettingRequest;
use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

use function Laravel\Ai\agent;

class AiSettingsController extends Controller
{
    public function index(): Response
    {
        $existing = AiSetting::query()->get()->keyBy(fn ($s) => $s->provider->value);

        $settings = collect(AiProvider::cases())->map(function (AiProvider $provider) use ($existing) {
            $setting = $existing->get($provider->value) ?? AiSetting::forProvider($provider);

            return [
                ...$this->serializeSetting($setting),
                'label' => $provider->label(),
                'supports_embeddings' => $provider->supportsEmbeddings(),
            ];
        });

        $book = Book::query()->select('id', 'title')->first();

        return Inertia::render('settings/ai-providers', [
            'settings' => $settings,
            'book' => $book?->only('id', 'title'),
        ]);
    }

    public function update(UpdateAiSettingRequest $request, AiProvider $provider): JsonResponse
    {
        $data = $request->validated();

        // If enabling this provider, use selectProvider to enforce single selection
        if (! empty($data['enabled'])) {
            $setting = AiSetting::selectProvider($provider);
        } else {
            $setting = AiSetting::forProvider($provider);
        }

        // Only update api_key if provided (don't clear on empty)
        if (! array_key_exists('api_key', $data) || $data['api_key'] === null) {
            unset($data['api_key']);
        }

        $setting->update($data);

        return response()->json([
            'message' => __(':provider settings updated.', ['provider' => $provider->label()]),
            'setting' => $this->serializeSetting($setting),
        ]);
    }

    public function test(AiProvider $provider): JsonResponse
    {
        $setting = AiSetting::forProvider($provider);

        if (! $setting->isConfigured()) {
            return response()->json(['success' => false, 'message' => __('No API key configured.')], 422);
        }

        $setting->injectConfig();

        try {
            agent(
                instructions: 'You are a connection tester. Respond with exactly: ok',
            )->prompt('Test connection.', provider: $provider->toLab()->value);

            return response()->json([
                'success' => true,
                'message' => __('Connection successful.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Connection failed: :error', ['error' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSetting(AiSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'provider' => $setting->provider->value,
            'has_api_key' => $setting->hasApiKey(),
            'base_url' => $setting->base_url,
            'api_version' => $setting->api_version,
            'text_model' => $setting->text_model,
            'embedding_model' => $setting->embedding_model,
            'embedding_dimensions' => $setting->embedding_dimensions,
            'enabled' => $setting->enabled,
            'requires_api_key' => $setting->provider->requiresApiKey(),
            'requires_base_url' => $setting->provider->requiresBaseUrl(),
        ];
    }
}

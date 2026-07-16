<?php

namespace App\Http\Controllers;

use App\Ai\Support\AiErrorClassifier;
use App\Enums\AiProvider;
use App\Http\Requests\UpdateAiSettingRequest;
use App\Models\AiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Laravel\Ai\agent;

class AiSettingsController extends Controller
{
    public function update(UpdateAiSettingRequest $request, AiProvider $provider): JsonResponse
    {
        $data = $request->validated();

        // Only update api_key if provided (don't clear on empty)
        if (! array_key_exists('api_key', $data) || $data['api_key'] === null) {
            unset($data['api_key']);
        }

        $setting = DB::transaction(function () use ($data, $provider): AiSetting {
            $setting = AiSetting::forProvider($provider);

            if (! $data['enabled']) {
                $setting->update($data);

                return $setting;
            }

            $setting->update(Arr::except($data, ['enabled']));

            if (! $setting->isConfigured()) {
                throw ValidationException::withMessages([
                    'enabled' => __('Configure this provider before selecting it.'),
                ]);
            }

            return AiSetting::selectProvider($provider);
        });

        return response()->json([
            'message' => __(':provider settings updated.', ['provider' => $provider->label()]),
            'setting' => $setting->toFrontendArray(),
        ]);
    }

    public function deleteKey(AiProvider $provider): JsonResponse
    {
        $setting = AiSetting::query()->where('provider', $provider)->first();

        if (! $setting?->hasApiKey()) {
            return response()->json(['message' => __('No API key to remove.')]);
        }

        $setting->update(['api_key' => null]);

        return response()->json([
            'message' => __(':provider API key removed.', ['provider' => $provider->label()]),
            'setting' => $setting->toFrontendArray(),
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
            $classified = AiErrorClassifier::classify($e, $provider->value);

            return response()->json([
                'success' => false,
                'kind' => $classified['kind'],
                'message' => __('Connection failed: :error', ['error' => $classified['message']]),
            ], 422);
        }
    }
}

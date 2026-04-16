<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

/**
 * Simulates a row encrypted with an APP_KEY the app no longer has.
 * Reproduces the MAC-invalid crash seen in Sentry issue 112306879 after
 * a release shipped with a freshly generated APP_KEY.
 */
function insertAiSettingWithStaleCipher(string $plaintext = 'sk-stale-key'): int
{
    $foreignKey = random_bytes(32);
    $foreignEncrypter = new Encrypter($foreignKey, config('app.cipher'));
    $staleCipher = $foreignEncrypter->encrypt($plaintext, false);

    return DB::table('ai_settings')->insertGetId([
        'provider' => AiProvider::Anthropic->value,
        'api_key' => $staleCipher,
        'enabled' => true,
        'api_key_recovery_needed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('dashboard does not crash when stored api_key was encrypted with a rotated APP_KEY', function () {
    insertAiSettingWithStaleCipher();

    $this->get('/')->assertOk();
});

test('reading a stale cipher marks the row for recovery and nulls the stored value', function () {
    $id = insertAiSettingWithStaleCipher();

    $setting = AiSetting::find($id);
    expect($setting->hasApiKey())->toBeFalse();

    $fresh = AiSetting::find($id);
    expect($fresh->api_key_recovery_needed)->toBeTrue();
    expect(DB::table('ai_settings')->where('id', $id)->value('api_key'))->toBeNull();
});

test('isConfigured returns false for a provider whose cipher has rotated', function () {
    $id = insertAiSettingWithStaleCipher();
    $setting = AiSetting::find($id);

    expect($setting->isConfigured())->toBeFalse();
});

test('toFrontendArray exposes the recovery flag and never leaks a crash', function () {
    $id = insertAiSettingWithStaleCipher();
    $setting = AiSetting::find($id);

    $payload = $setting->toFrontendArray();

    expect($payload['has_api_key'])->toBeFalse()
        ->and($payload['masked_api_key'])->toBeNull()
        ->and($payload['api_key_recovery_needed'])->toBeTrue();
});

test('recovery is idempotent — a second read does not re-null or re-flag', function () {
    $id = insertAiSettingWithStaleCipher();

    AiSetting::find($id)->hasApiKey(); // first read triggers recovery

    $updatedAt = DB::table('ai_settings')->where('id', $id)->value('updated_at');

    // Second read on a fresh model should be a no-op on the DB row.
    AiSetting::find($id)->hasApiKey();

    expect(DB::table('ai_settings')->where('id', $id)->value('updated_at'))
        ->toBe($updatedAt);
});

test('Inertia share exposes ai_key_recovery_needed so the UI can surface a banner', function () {
    insertAiSettingWithStaleCipher();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ai_configured', false)
            ->where('ai_key_recovery_needed', true)
        );
});

test('a healthy api_key is unaffected by the recovery path', function () {
    $setting = AiSetting::factory()->create(['api_key' => 'sk-healthy']);

    expect($setting->hasApiKey())->toBeTrue()
        ->and($setting->isConfigured())->toBeTrue()
        ->and($setting->api_key_recovery_needed)->toBeFalse()
        ->and($setting->decryptedApiKey())->toBe('sk-healthy');
});

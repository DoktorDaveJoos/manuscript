import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { index as settingsIndex } from '@/routes/settings';

export type AiErrorPayload = {
    kind: string;
    message?: string | null;
    provider?: string | null;
};

const PROVIDER_LABELS: Record<string, string> = {
    anthropic: 'Anthropic',
    openai: 'OpenAI',
    azure: 'Azure',
    gemini: 'Gemini',
    groq: 'Groq',
    mistral: 'Mistral',
    deepseek: 'DeepSeek',
    xai: 'xAI',
    openrouter: 'OpenRouter',
    ollama: 'Ollama',
    cohere: 'Cohere',
    bedrock: 'AWS Bedrock',
};

/**
 * Returns a stable callback that takes a backend-classified AI error
 * payload and renders the appropriate Sonner toast. Each kind has its
 * own i18n string and may attach an action button (e.g. "Open settings"
 * for invalid_key / model_unavailable).
 *
 * The hook itself does not retain state; it just wires translations and
 * the settings-route navigation into the toast call site.
 */
export function useAiErrorToast(): (payload: AiErrorPayload) => void {
    const { t } = useTranslation('ai');

    return (payload: AiErrorPayload) => {
        const kind = isKnownKind(payload.kind) ? payload.kind : 'unknown';
        const provider =
            (payload.provider && PROVIDER_LABELS[payload.provider]) ||
            t('error.toast.providerFallback');

        const detail = (payload.message ?? '').trim();

        const title = t(`error.toast.${kind}.title`);
        const description = t(`error.toast.${kind}.body`, {
            provider,
            detail: detail !== '' ? detail : title,
        });

        const action = needsSettingsAction(kind)
            ? {
                  label: t('error.toast.action.openSettings'),
                  onClick: () => router.visit(settingsIndex.url()),
              }
            : undefined;

        toast.error(title, {
            description,
            action,
            duration: persistent(kind) ? Infinity : 6000,
        });
    };
}

const KNOWN_KINDS = [
    'invalid_key',
    'insufficient_credits',
    'rate_limited',
    'overloaded',
    'model_unavailable',
    'context_too_long',
    'bad_request',
    'timeout',
    'unknown',
] as const;

function isKnownKind(kind: string): kind is (typeof KNOWN_KINDS)[number] {
    return (KNOWN_KINDS as readonly string[]).includes(kind);
}

// Errors that block all further chat until the user takes action stay on
// screen. Transient errors (rate limit, overload, timeout) auto-dismiss.
function persistent(kind: string): boolean {
    return (
        kind === 'invalid_key' ||
        kind === 'insufficient_credits' ||
        kind === 'model_unavailable' ||
        kind === 'context_too_long'
    );
}

function needsSettingsAction(kind: string): boolean {
    return kind === 'invalid_key' || kind === 'model_unavailable';
}

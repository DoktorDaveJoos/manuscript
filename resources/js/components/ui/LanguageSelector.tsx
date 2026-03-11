import { update } from '@/actions/App/Http/Controllers/AppSettingsController';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

const LOCALES = ['en', 'de'] as const;

export default function LanguageSelector() {
    const { i18n } = useTranslation();
    const currentLocale = usePage<{ locale: string }>().props.locale ?? 'en';
    const activeLocale = i18n.language || currentLocale;

    function switchLocale(locale: string) {
        if (locale === activeLocale) return;

        i18n.changeLanguage(locale);

        fetch(update.url(), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
            body: JSON.stringify({ key: 'locale', value: locale }),
        });
    }

    return (
        <div className="flex items-center gap-0.5 text-[11px] font-medium tracking-wide">
            {LOCALES.map((locale) => (
                <button
                    key={locale}
                    onClick={() => switchLocale(locale)}
                    className={`rounded px-1 py-0.5 uppercase transition-colors ${
                        activeLocale === locale
                            ? 'text-ink'
                            : 'text-ink-faint hover:text-ink-muted'
                    }`}
                >
                    {locale}
                </button>
            ))}
        </div>
    );
}

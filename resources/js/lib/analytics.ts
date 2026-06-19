import type { trackEvent as aptabaseTrackEvent } from '@aptabase/web';

type AnalyticsProperties = Record<string, string | number | boolean>;

let trackAptabaseEvent: typeof aptabaseTrackEvent | null = null;
let enabled = true;

export async function initAnalytics({
    version,
    enabled: initialEnabled,
}: {
    version: string;
    enabled: boolean;
}): Promise<void> {
    enabled = initialEnabled;

    const appKey = import.meta.env.VITE_APTABASE_KEY;
    if (!appKey) {
        return;
    }

    try {
        const aptabase = await import('@aptabase/web');
        aptabase.init(appKey, { appVersion: version });
        trackAptabaseEvent = aptabase.trackEvent;
    } catch {
        trackAptabaseEvent = null;
    }
}

export function setAnalyticsEnabled(nextEnabled: boolean): void {
    enabled = nextEnabled;
}

export function track(name: string, properties?: AnalyticsProperties): void {
    if (!enabled || !trackAptabaseEvent) {
        return;
    }

    void trackAptabaseEvent(name, properties);
}

export function screenNameFor(component: string): string {
    if (component.startsWith('chapters/')) {
        return 'editor';
    }

    if (component === 'books/dashboard') {
        return 'dashboard';
    }

    if (component === 'books/index') {
        return 'book_list';
    }

    if (
        component.startsWith('settings/') ||
        component.startsWith('books/settings/')
    ) {
        return 'settings';
    }

    if (component.startsWith('plot/') || component.startsWith('canvas/')) {
        return 'plot_canvas';
    }

    if (component === 'books/export') {
        return 'export';
    }

    if (component === 'books/editorial-review') {
        return 'editorial_review';
    }

    if (component === 'license/welcome' || component === 'books/import') {
        return 'onboarding';
    }

    return 'other';
}

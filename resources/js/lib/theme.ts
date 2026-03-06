export type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'manuscript:theme';

function getSystemPreference(): 'light' | 'dark' {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme: Theme): void {
    const resolved = theme === 'system' ? getSystemPreference() : theme;
    document.documentElement.classList.toggle('dark', resolved === 'dark');
}

export function getTheme(): Theme {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
    } catch {
        // Ignore storage errors
    }
    return 'system';
}

export function setTheme(theme: Theme): void {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Ignore storage errors
    }
    applyTheme(theme);
}

export function initTheme(): void {
    applyTheme(getTheme());

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getTheme() === 'system') {
            applyTheme('system');
        }
    });
}

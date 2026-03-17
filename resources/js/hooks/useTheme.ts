import { useCallback, useEffect, useState } from 'react';
import { getTheme, setTheme as setThemeRaw } from '@/lib/theme';
import type { Theme } from '@/lib/theme';

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(getTheme);

    const setTheme = useCallback((t: Theme) => {
        setThemeRaw(t);
        setThemeState(t);
    }, []);

    useEffect(() => {
        const handler = () => setThemeState(getTheme());
        window.addEventListener('storage', handler);
        return () => window.removeEventListener('storage', handler);
    }, []);

    return { theme, setTheme };
}

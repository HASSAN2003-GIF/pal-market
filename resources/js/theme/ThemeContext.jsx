import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

/**
 * Light, dark or "same as my device". The choice is stored on this device;
 * public/theme-init.js applies it before first paint.
 */
export const THEME_STORAGE_KEY = 'pal_market_theme';
export const THEMES = ['light', 'dark', 'system'];
const THEME_COLOR = { light: '#FFFFFF', dark: '#11171D' };

const ThemeContext = createContext(null);

function savedChoice() {
    try {
        const saved = localStorage.getItem(THEME_STORAGE_KEY);
        return THEMES.includes(saved) ? saved : 'system';
    } catch {
        return 'system';
    }
}

const systemQuery = () => window.matchMedia?.('(prefers-color-scheme: dark)');

function apply(resolved) {
    const root = document.documentElement;
    root.dataset.theme = resolved;
    root.style.colorScheme = resolved;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_COLOR[resolved]);
}

export function ThemeProvider({ children }) {
    const [choice, setChoiceState] = useState(savedChoice);
    const [systemDark, setSystemDark] = useState(() => Boolean(systemQuery()?.matches));

    useEffect(() => {
        const query = systemQuery();
        if (!query) return undefined;
        const onChange = (event) => setSystemDark(event.matches);
        query.addEventListener('change', onChange);
        return () => query.removeEventListener('change', onChange);
    }, []);

    const resolved = choice === 'system' ? (systemDark ? 'dark' : 'light') : choice;

    useEffect(() => apply(resolved), [resolved]);

    const setChoice = useCallback((next) => {
        if (!THEMES.includes(next)) return;
        setChoiceState(next);
        try {
            localStorage.setItem(THEME_STORAGE_KEY, next);
        } catch {
            // Lasts for this visit only.
        }
    }, []);

    const value = useMemo(() => ({ choice, resolved, setChoice }), [choice, resolved, setChoice]);

    return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) throw new Error('useTheme must be used inside ThemeProvider.');
    return context;
}

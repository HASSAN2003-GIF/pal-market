import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import en from './locales/en';
import sw from './locales/sw';

/**
 * English / Kiswahili interface text.
 *
 * - t('ns.key', { name }) replaces {name} placeholders.
 * - Pass { count } to pick 'key_one' / 'key_other' with Intl.PluralRules.
 * - Missing Swahili text falls back to English; a missing key shows the key
 *   (and warns in development). `npm run check:i18n` catches both before release.
 * - Only interface text goes through here. Names, descriptions and other text
 *   people typed are shown exactly as entered.
 */
export const LOCALES = ['en', 'sw'];
export const LOCALE_STORAGE_KEY = 'pal_market_locale';
const DICTIONARIES = { en, sw };
const INTL = { en: 'en-TZ', sw: 'sw-TZ' };

function initialLocale() {
    try {
        const saved = localStorage.getItem(LOCALE_STORAGE_KEY);
        if (LOCALES.includes(saved)) return saved;
    } catch {
        // Storage blocked: fall through to the browser language.
    }
    const browser = (navigator.languages ?? [navigator.language]).find((l) => /^(sw|en)\b/i.test(l ?? ''));
    return browser?.toLowerCase().startsWith('sw') ? 'sw' : 'en';
}

let currentLocale = typeof window === 'undefined' ? 'en' : initialLocale();
const listeners = new Set();

export function getLocale() {
    return currentLocale;
}

/** BCP 47 tag for Intl formatting of dates and numbers. */
export function intlLocale(locale = currentLocale) {
    return INTL[locale] ?? INTL.en;
}

function lookup(locale, key, count) {
    const dictionary = DICTIONARIES[locale];
    if (count !== undefined) {
        const form = new Intl.PluralRules(intlLocale(locale)).select(Number(count));
        const plural = dictionary[`${key}_${form}`] ?? dictionary[`${key}_other`];
        if (plural !== undefined) return plural;
    }
    return dictionary[key];
}

export function translate(key, vars = {}, locale = currentLocale) {
    let text = lookup(locale, key, vars.count) ?? lookup('en', key, vars.count);
    if (text === undefined) {
        if (import.meta.env?.DEV) console.warn(`[i18n] Missing text for "${key}"`);
        return key;
    }
    return text.replace(/\{(\w+)\}/g, (match, name) => (vars[name] !== undefined && vars[name] !== null ? String(vars[name]) : match));
}

function applyLocale(locale) {
    currentLocale = locale;
    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    } catch {
        // Not persisted in private mode; the choice lasts for this visit.
    }
    document.documentElement.lang = locale;
    listeners.forEach((listener) => listener(locale));
}

const I18nContext = createContext(null);

export function I18nProvider({ children }) {
    const [locale, setLocaleState] = useState(currentLocale);

    useEffect(() => {
        document.documentElement.lang = locale;
        const listener = (next) => setLocaleState(next);
        listeners.add(listener);
        return () => listeners.delete(listener);
    }, [locale]);

    const setLocale = useCallback((next) => {
        if (LOCALES.includes(next) && next !== currentLocale) applyLocale(next);
    }, []);

    const value = useMemo(
        () => ({ locale, setLocale, t: (key, vars) => translate(key, vars, locale) }),
        [locale, setLocale],
    );

    return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
    const context = useContext(I18nContext);
    if (!context) throw new Error('useI18n must be used inside I18nProvider.');
    return context;
}

/** Shorthand for components that only need t(). */
export function useT() {
    return useI18n().t;
}

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useI18n } from '../i18n';
import { api } from '../lib/api';
import { isRegion } from './regions';

/**
 * The person's area (a region name, never coordinates) and keeping it, and
 * the interface language, in step with their account when they are signed in.
 */
export const REGION_STORAGE_KEY = 'pal_market_region';
const LocationContext = createContext(null);

function readSaved() {
    try {
        const saved = localStorage.getItem(REGION_STORAGE_KEY);
        return isRegion(saved) ? saved : null;
    } catch {
        return null;
    }
}

function writeSaved(region) {
    try {
        if (region) localStorage.setItem(REGION_STORAGE_KEY, region);
        else localStorage.removeItem(REGION_STORAGE_KEY);
    } catch {
        // Lasts for this visit only.
    }
}

export function LocationProvider({ children }) {
    const { user, setUser } = useAuth();
    const { locale, setLocale } = useI18n();
    const [region, setRegionState] = useState(readSaved);
    const syncedUserId = useRef(null);

    // On sign-in, the account's saved choices win; anything chosen before
    // signing in is saved to an account that has none yet.
    useEffect(() => {
        if (!user) {
            syncedUserId.current = null;
            return;
        }
        if (syncedUserId.current === user.id) return;
        syncedUserId.current = user.id;

        const changes = {};
        if (user.locale) setLocale(user.locale);
        else changes.locale = locale;

        if (user.region && isRegion(user.region)) {
            setRegionState(user.region);
            writeSaved(user.region);
        } else if (region) {
            changes.region = region;
        }

        if (Object.keys(changes).length) {
            api.updatePreferences(changes)
                .then(({ user: updated }) => setUser(updated))
                .catch(() => {});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user?.id]);

    // Later language changes follow the account.
    useEffect(() => {
        if (user && syncedUserId.current === user.id && user.locale !== locale) {
            api.updatePreferences({ locale })
                .then(({ user: updated }) => setUser(updated))
                .catch(() => {});
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [locale]);

    const setRegion = useCallback(
        (next) => {
            const value = isRegion(next) ? next : null;
            setRegionState(value);
            writeSaved(value);
            if (user) {
                api.updatePreferences({ region: value })
                    .then(({ user: updated }) => setUser(updated))
                    .catch(() => {});
            }
        },
        [user, setUser],
    );

    const value = useMemo(() => ({ region, setRegion, clearRegion: () => setRegion(null) }), [region, setRegion]);

    return <LocationContext.Provider value={value}>{children}</LocationContext.Provider>;
}

/** The person's chosen region and setters ("area" avoids clashing with the router's useLocation). */
export function useArea() {
    const context = useContext(LocationContext);
    if (!context) throw new Error('useArea must be used inside LocationProvider.');
    return context;
}

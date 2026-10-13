import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api, getToken, onUnauthorized, setToken } from '../lib/api';

const AuthContext = createContext(null);

/** Where each role lands after signing in. */
export function homePathFor(user) {
    if (!user) return '/login';
    if (user.role === 'admin') return '/admin';
    if (user.role === 'supplier') return '/supplier';
    return '/buyer';
}

export function AuthProvider({ children }) {
    const queryClient = useQueryClient();
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [sessionNotice, setSessionNotice] = useState(null);

    const clearSession = useCallback(
        (reason) => {
            setToken(null);
            setUser(null);
            queryClient.clear();
            if (reason === 'expired') setSessionNotice('Your session has ended. Please sign in again.');
            if (reason === 'suspended') setSessionNotice('This account has been suspended. Please contact PAL Market support.');
        },
        [queryClient],
    );

    const loadUser = useCallback(async () => {
        if (!getToken()) {
            setUser(null);
            setLoading(false);
            return null;
        }
        try {
            const { user: current } = await api.me();
            setUser(current);
            return current;
        } catch {
            clearSession();
            return null;
        } finally {
            setLoading(false);
        }
    }, [clearSession]);

    useEffect(() => {
        onUnauthorized(clearSession);
        loadUser();
    }, [clearSession, loadUser]);

    const startSession = useCallback((response) => {
        setToken(response.token);
        setUser(response.user);
        setSessionNotice(null);
        return response.user;
    }, []);

    const value = useMemo(
        () => ({
            user,
            loading,
            isAuthenticated: Boolean(user),
            sessionNotice,
            dismissSessionNotice: () => setSessionNotice(null),
            login: async (credentials) => startSession(await api.login(credentials)),
            register: async (payload) => startSession(await api.register(payload)),
            completeGoogleSignIn: async (code) => startSession(await api.exchangeGoogleCode(code)),
            refreshUser: loadUser,
            setUser,
            logout: async () => {
                try {
                    await api.logout();
                } catch {
                    // Token may already be invalid; clear locally regardless.
                } finally {
                    clearSession();
                }
            },
        }),
        [user, loading, sessionNotice, startSession, loadUser, clearSession],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error('useAuth must be used inside AuthProvider.');
    return context;
}

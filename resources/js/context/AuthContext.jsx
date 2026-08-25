import { createContext, useContext, useEffect, useState } from 'react';
import {
    getCurrentUser,
    loginUser,
    logoutUser,
    registerUser,
} from '../lib/api';

const AuthContext = createContext(null);

const TOKEN_KEY = 'pal_market_token';

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    async function loadUser() {
        const token = localStorage.getItem(TOKEN_KEY);

        if (!token) {
            setLoading(false);
            return;
        }

        try {
            const response = await getCurrentUser();

            setUser(response.user);
        } catch {
            localStorage.removeItem(TOKEN_KEY);
            setUser(null);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadUser();
    }, []);

    async function login(credentials) {
        const response = await loginUser(credentials);

        localStorage.setItem(TOKEN_KEY, response.token);
        setUser(response.user);

        return response;
    }

    async function register(payload) {
        const response = await registerUser(payload);

        localStorage.setItem(TOKEN_KEY, response.token);
        setUser(response.user);

        return response;
    }

    async function logout() {
        try {
            await logoutUser();
        } finally {
            localStorage.removeItem(TOKEN_KEY);
            setUser(null);
        }
    }

    const value = {
        user,
        loading,
        isAuthenticated: Boolean(user),
        login,
        register,
        logout,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (!context) {
        throw new Error('useAuth must be used inside AuthProvider.');
    }

    return context;
}
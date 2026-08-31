import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext'; // <-- Restoring your auth context
import AppRouter from './routes/AppRouter';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1,
            staleTime: 1000 * 60 * 5,
        },
    },
});

const rootElement = document.getElementById('app');
if (rootElement) {
    const root = createRoot(rootElement);
    root.render(
        <QueryClientProvider client={queryClient}>
            <AuthProvider>
                <AppRouter />
            </AuthProvider>
        </QueryClientProvider>
    );
}
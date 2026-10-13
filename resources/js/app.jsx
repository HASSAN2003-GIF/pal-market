import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import { ToastProvider } from './components/ui';
import AppRouter from './routes/AppRouter';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            staleTime: 30_000,
            // Never retry client errors (403/404/422); retry transient ones once.
            retry: (failureCount, error) => failureCount < 1 && (error?.status === 0 || error?.status >= 500),
        },
    },
});

const rootElement = document.getElementById('app');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <ToastProvider>
                    <AuthProvider>
                        <AppRouter />
                    </AuthProvider>
                </ToastProvider>
            </QueryClientProvider>
        </StrictMode>,
    );
}

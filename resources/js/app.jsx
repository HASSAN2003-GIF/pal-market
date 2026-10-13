import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import { ToastProvider } from './components/ui';
import { I18nProvider } from './i18n';
import { LocationProvider } from './location/LocationContext';
import { ThemeProvider } from './theme/ThemeContext';
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
            <I18nProvider>
                <ThemeProvider>
                    <QueryClientProvider client={queryClient}>
                        <ToastProvider>
                            <AuthProvider>
                                <LocationProvider>
                                    <AppRouter />
                                </LocationProvider>
                            </AuthProvider>
                        </ToastProvider>
                    </QueryClientProvider>
                </ThemeProvider>
            </I18nProvider>
        </StrictMode>,
    );
}

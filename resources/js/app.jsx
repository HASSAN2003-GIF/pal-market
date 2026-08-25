import React from 'react';
import { createRoot } from 'react-dom/client';

import '../css/app.css';
import AppRouter from './routes/AppRouter';
import { AuthProvider } from './context/AuthContext';

const rootElement = document.getElementById('app');

if (rootElement) {
    createRoot(rootElement).render(
        <React.StrictMode>
            <AuthProvider>
                <AppRouter />
            </AuthProvider>
        </React.StrictMode>,
    );
}
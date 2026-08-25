import React from 'react';
import { createRoot } from 'react-dom/client';

import '../css/app.css';
import AppRouter from './routes/AppRouter';

const rootElement = document.getElementById('app');

if (rootElement) {
    createRoot(rootElement).render(
        <React.StrictMode>
            <AppRouter />
        </React.StrictMode>,
    );
}
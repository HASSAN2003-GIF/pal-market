import { cpSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

/**
 * Builds the React app as a static site for Vercel (`npm run build:vercel`,
 * output in dist/). The Laravel API runs elsewhere (Render); vercel.json
 * forwards /api, /media and the Google sign-in routes to it, so the browser
 * only ever talks to one origin.
 */
const root = resolve(import.meta.dirname);

// Only these public files are needed by the SPA (public/ also holds
// index.php and .htaccess, which must not be published).
const STATIC_FILES = ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'theme-init.js', 'robots.txt', 'brand'];

function copyStaticFiles() {
    return {
        name: 'pal-copy-static-files',
        closeBundle() {
            mkdirSync(resolve(root, 'dist'), { recursive: true });
            for (const file of STATIC_FILES) {
                cpSync(resolve(root, 'public', file), resolve(root, 'dist', file), { recursive: true });
            }
        },
    };
}

export default defineConfig({
    root: resolve(root, 'deploy/vercel'),
    publicDir: false,
    plugins: [react(), tailwindcss(), copyStaticFiles()],
    build: {
        outDir: resolve(root, 'dist'),
        emptyOutDir: true,
    },
});

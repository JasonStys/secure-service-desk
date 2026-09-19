/**
 * File: Configures deterministic Vite builds for the PHP-rendered application.
 * Symbols: default Vite configuration and Laravel plugin inputs.
 * State: CSS/JavaScript entry paths and file-watch exclusions; see docs/code-index.md.
 */

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

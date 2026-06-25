import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: [
                    'import',
                    'global-builtin',
                    'color-functions',
                    'if-function',
                ],
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/filament/admin/theme.css',
                'resources/scss/app-public.scss',
                'resources/scss/app-operativo.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Vite genera <link rel="modulepreload"> para CSS por defecto,
        // pero Livewire navega sin recargar la página y el navegador
        // marca el preload como "no usado" en consola. Desactivando el
        // polyfill se suprime el aviso sin afectar al funcionamiento.
        modulePreload: { polyfill: false },
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (! id.includes('node_modules')) {
                        return;
                    }

                    if (id.includes('/bootstrap') || id.includes('/@popperjs/')) {
                        return 'vendor-bootstrap';
                    }

                    return 'vendor';
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

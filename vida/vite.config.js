import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/app-operativo.css',
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
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

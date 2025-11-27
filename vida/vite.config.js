import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Copia fuentes a public/build (para offline)
    build: {
        assetsInlineLimit: 0,  // No inline fonts
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.woff2')) {
                        return 'assets/fonts/[name][extname]';
                    }
                    return 'assets/[name]-[hash][extname]';
                }
            }
        }
    }
});

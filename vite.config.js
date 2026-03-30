import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // --- TAMBAHKAN BAGIAN INI ---
    server: {
        host: '0.0.0.0', // Supaya bisa diakses dari luar container
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost', // Browser Windows konek ke sini
        },
        // --- TAMBAHKAN INI AGAR AUTO-UPDATE ---
        watch: {
            usePolling: true, 
            interval: 100,
        },
    },
});
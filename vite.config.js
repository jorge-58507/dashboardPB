import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0', // Esto permite el acceso desde cualquier IP
        port: 5173,      // O el puerto que estés usando (el 5173 es el predeterminado)
        cors: true,
        hmr: {
            // Esto es crucial para que las actualizaciones en caliente (HMR) funcionen
            host: '192.168.2.156',
        }
    }
});

import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

const php =
    process.platform === 'win32' && process.env.USERPROFILE
        ? `"${process.env.USERPROFILE}\\.config\\herd\\bin\\php84\\php.exe"`
        : 'php';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        process.env.SKIP_WAYFINDER === '1'
            ? null
            : wayfinder({
                  formVariants: true,
                  command: `${php} artisan wayfinder:generate`,
              }),
    ],
});

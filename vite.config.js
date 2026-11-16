import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// N03: frontend of the rebuilt pages (layouts/modern). Legacy pages keep their prebuilt files in public/css, public/js.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/modern/app.scss', 'resources/modern/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            // Bootstrap 5.3 still uses @import and colour functions that Dart Sass reports as deprecated
            scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions'] },
        },
    },
});

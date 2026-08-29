import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                compilerOptions: {
                    // <h5p-editor>/<h5p-player> are native Custom Elements
                    // registered by @lumieducation/h5p-webcomponents, not
                    // Vue components — don't warn about them being unresolved.
                    isCustomElement: (tag) => tag.startsWith('h5p-'),
                },
            },
        }),
        tailwindcss(),
    ],
})
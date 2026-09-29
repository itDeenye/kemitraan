import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import VueRouter from 'unplugin-vue-router/vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
    plugins: [
        VueRouter({
            routesFolder: [
                { src: 'resources/js/admin/pages', path: 'admin/' },
                { src: 'resources/js/member/pages', path: 'member/' },
            ],
            dts: 'resources/js/typed-router.d.ts',
        }),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/member/app.ts',
                'resources/js/admin/app.ts',
            ],
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
        vuetify({ autoImport: true }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
})

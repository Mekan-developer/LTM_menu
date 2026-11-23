import '../css/app.css';
import './bootstrap.js';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Страницы MVP написаны на JS (<script setup>), Vite собирает их как есть.
void createInertiaApp({
    title: (title) => (title ? `${title} · CRM` : 'CRM'),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el!);
    },
    progress: { color: '#A8472A', delay: 150 },
});

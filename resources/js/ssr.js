import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { I18nInjectionKey } from 'vue-i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n)
                .use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                });

            app.provide(I18nInjectionKey, i18n);
            app.config.globalProperties.$t = i18n.global.t.bind(i18n.global);
            app.config.globalProperties.$te = i18n.global.te.bind(i18n.global);
            app.config.globalProperties.$d = i18n.global.d.bind(i18n.global);
            app.config.globalProperties.$n = i18n.global.n.bind(i18n.global);

            return app;
        },
    }),
);

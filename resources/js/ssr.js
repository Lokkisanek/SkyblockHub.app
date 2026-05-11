import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
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

            const safeGlobal = (i18n && i18n.global) ? i18n.global : i18n || {};
            app.config.globalProperties.$t =
                typeof safeGlobal.t === 'function' ? safeGlobal.t.bind(safeGlobal) : ((k) => k);
            app.config.globalProperties.$te =
                typeof safeGlobal.te === 'function' ? safeGlobal.te.bind(safeGlobal) : (() => false);
            app.config.globalProperties.$d =
                typeof safeGlobal.d === 'function' ? safeGlobal.d.bind(safeGlobal) : (() => undefined);
            app.config.globalProperties.$n =
                typeof safeGlobal.n === 'function' ? safeGlobal.n.bind(safeGlobal) : (() => undefined);

            return app;
        },
    }),
);

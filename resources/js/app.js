import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import * as Sentry from '@sentry/vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'SkyblockHub';
const appRelease = import.meta.env.VITE_APP_RELEASE || import.meta.env.VITE_SENTRY_RELEASE;

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        const sentryDsn = import.meta.env.VITE_SENTRY_DSN;

        if (sentryDsn && import.meta.env.PROD) {
            Sentry.init({
                app,
                dsn: sentryDsn,
                environment: import.meta.env.VITE_SENTRY_ENV || import.meta.env.MODE,
                release: appRelease || undefined,
                tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || 0),
            });
        }

        // Defensive bindings: i18n may be a lightweight plugin with a minimal shape.
        const safeGlobal = (i18n && i18n.global) ? i18n.global : i18n || {};
        const safeT = typeof safeGlobal.t === 'function' ? safeGlobal.t.bind(safeGlobal) : (k => (typeof safeGlobal.t === 'function' ? safeGlobal.t(k) : k));
        const safeTe = typeof safeGlobal.te === 'function' ? safeGlobal.te.bind(safeGlobal) : (k => (typeof safeGlobal.te === 'function' ? safeGlobal.te(k) : false));
        const safeD = typeof safeGlobal.d === 'function' ? safeGlobal.d.bind(safeGlobal) : (() => undefined);
        const safeN = typeof safeGlobal.n === 'function' ? safeGlobal.n.bind(safeGlobal) : (() => undefined);

        app.config.globalProperties.$t = safeT;
        app.config.globalProperties.$te = safeTe;
        app.config.globalProperties.$d = safeD;
        app.config.globalProperties.$n = safeN;

        return app
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

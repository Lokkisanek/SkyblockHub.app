import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import * as Sentry from '@sentry/vue';
import { I18nInjectionKey } from 'vue-i18n';
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

        if (sentryDsn) {
            Sentry.init({
                app,
                dsn: sentryDsn,
                environment: import.meta.env.VITE_SENTRY_ENV || import.meta.env.MODE,
                release: appRelease || undefined,
                tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || 0),
            });
        }

        app.provide(I18nInjectionKey, i18n);
        app.config.globalProperties.$t = i18n.global.t.bind(i18n.global);
        app.config.globalProperties.$te = i18n.global.te.bind(i18n.global);
        app.config.globalProperties.$d = i18n.global.d.bind(i18n.global);
        app.config.globalProperties.$n = i18n.global.n.bind(i18n.global);

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

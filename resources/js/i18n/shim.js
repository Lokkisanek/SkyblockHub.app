import { getCurrentInstance, inject, ref } from 'vue';

export const I18nInjectionKey = Symbol('SkyblockHubI18n');

function fallbackTranslate(key, params = {}) {
    if (typeof key !== 'string' || key.length === 0) {
        return '';
    }

    return key.replace(/\{(\w+)\}/g, (_, name) => {
        if (Object.prototype.hasOwnProperty.call(params, name)) {
            return String(params[name]);
        }

        return `{${name}}`;
    });
}

export function useI18n() {
    const instance = getCurrentInstance();
    const injected = inject(I18nInjectionKey, null);
    const globals = instance?.appContext?.config?.globalProperties || {};

    const tSource =
        (injected && typeof injected.t === 'function' && injected.t.bind(injected)) ||
        (typeof globals.$t === 'function' && globals.$t.bind(globals)) ||
        fallbackTranslate;
    const teSource =
        (injected && typeof injected.te === 'function' && injected.te.bind(injected)) ||
        (typeof globals.$te === 'function' && globals.$te.bind(globals)) ||
        ((k) => typeof k === 'string' && k.length > 0);
    const tmSource =
        (injected && typeof injected.tm === 'function' && injected.tm.bind(injected)) ||
        (typeof globals.$tm === 'function' && globals.$tm.bind(globals)) ||
        ((key) => key);  // Return raw key as fallback
    const rtSource =
        (injected && typeof injected.rt === 'function' && injected.rt.bind(injected)) ||
        (typeof globals.$rt === 'function' && globals.$rt.bind(globals)) ||
        ((msg) => msg);  // Return message as-is

    return {
        t: (key, params) => tSource(key, params),
        te: (key) => teSource(key),
        tm: (key) => tmSource(key),
        rt: (msg) => rtSource(msg),
        locale: ref('en'),
    };
}

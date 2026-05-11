// English-only translation plugin with a minimal API.
import { defineComponent, h, markRaw } from 'vue';
import en from './en.json';
import { I18nInjectionKey } from './shim';

const messages = markRaw(en || {});

function lookup(path, params = {}) {
    if (!path) return '';
    const parts = path.split('.');
    let cur = messages;
    for (const p of parts) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
            cur = cur[p];
        } else {
            return path; // fallback to key if missing
        }
    }
    const value = typeof cur === 'string' ? cur : JSON.stringify(cur);

    return value.replace(/\{(\w+)\}/g, (_, name) => {
        if (Object.prototype.hasOwnProperty.call(params, name)) {
            return String(params[name]);
        }

        return `{${name}}`;
    });
}

function hasKey(path) {
    if (!path || typeof path !== 'string') return false;
    const parts = path.split('.');
    let cur = messages;
    for (const p of parts) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
            cur = cur[p];
        } else {
            return false;
        }
    }
    return true;
}

function tokenizeTemplate(value) {
    const tokens = [];
    const regex = /\{(\w+)\}/g;
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(value)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ type: 'text', value: value.slice(lastIndex, match.index) });
        }

        tokens.push({ type: 'slot', value: match[1] });
        lastIndex = regex.lastIndex;
    }

    if (lastIndex < value.length) {
        tokens.push({ type: 'text', value: value.slice(lastIndex) });
    }

    return tokens;
}

const i18n = {
    // plugin install for Vue
    install(app) {
        if (app.config.globalProperties.__skyblockhubI18nInstalled) {
            return;
        }

        app.config.globalProperties.__skyblockhubI18nInstalled = true;
        app.config.globalProperties.$t = (k) => lookup(k);
        app.config.globalProperties.$te = (k) => hasKey(k);
        app.config.globalProperties.$tm = (k) => {
            if (!k) return undefined;
            const parts = k.split('.');
            let cur = messages;
            for (const p of parts) {
                if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
                    cur = cur[p];
                } else {
                    return undefined;
                }
            }
            return cur;
        };
        app.config.globalProperties.$rt = (msg) => msg;
        app.config.globalProperties.$d = () => undefined;
        app.config.globalProperties.$n = () => undefined;
        app.component('i18n-t', defineComponent({
            name: 'I18nT',
            props: {
                keypath: {
                    type: String,
                    required: true,
                },
                tag: {
                    type: String,
                    default: 'span',
                },
            },
            setup(props, { slots, attrs }) {
                return () => {
                    const template = lookup(props.keypath);
                    const tokens = tokenizeTemplate(template);
                    const children = tokens.map((token, index) => {
                        if (token.type === 'text') {
                            return token.value;
                        }

                        const slotRenderer = slots[token.value];
                        if (typeof slotRenderer === 'function') {
                            return h('span', { key: `slot-${index}` }, slotRenderer());
                        }

                        return `{${token.value}}`;
                    });

                    return h(props.tag, attrs, children);
                };
            },
        }));
        // provide a minimal object under the standard injection key so
        // existing `useI18n()` calls from components keep working.
        app.provide(I18nInjectionKey, {
            global: {
                t: (k, params) => lookup(k, params),
                te: (k) => hasKey(k),
                tm: (k) => {
                    // Return the raw message object/array/string at the key path
                    if (!k) return undefined;
                    const parts = k.split('.');
                    let cur = messages;
                    for (const p of parts) {
                        if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
                            cur = cur[p];
                        } else {
                            return undefined;
                        }
                    }
                    return cur;
                },
                rt: (msg) => msg, // rt just returns the message as-is
                d: () => undefined,
                n: () => undefined,
            },
            t: (k, params) => lookup(k, params),
            te: (k) => hasKey(k),
            tm: (k) => {
                // Return the raw message object/array/string at the key path
                if (!k) return undefined;
                const parts = k.split('.');
                let cur = messages;
                for (const p of parts) {
                    if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
                        cur = cur[p];
                    } else {
                        return undefined;
                    }
                }
                return cur;
            },
            rt: (msg) => msg, // rt just returns the message as-is
            d: () => undefined,
            n: () => undefined,
        });
    },
    // expose global for direct bindings used in app.js
    global: {
        t: (k, params) => lookup(k, params),
        te: (k) => hasKey(k),
        tm: (k) => {
            // Return the raw message object/array/string at the key path
            if (!k) return undefined;
            const parts = k.split('.');
            let cur = messages;
            for (const p of parts) {
                if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
                    cur = cur[p];
                } else {
                    return undefined;
                }
            }
            return cur;
        },
        rt: (msg) => msg, // rt just returns the message as-is
        d: () => undefined,
        n: () => undefined,
    },
};

export default i18n;

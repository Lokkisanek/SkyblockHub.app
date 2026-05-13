import { defineComponent, h } from 'vue';
import { getRaw, hasKey, I18nInjectionKey, lookup, tokenizeTemplate } from './core';

function makeProvideValue() {
    const global = {
        t: (k, params) => lookup(k, params),
        te: (k) => hasKey(k),
        tm: (k) => getRaw(k),
        rt: (msg) => msg,
        d: () => undefined,
        n: () => undefined,
    };

    return {
        global,
        t: (k, params) => lookup(k, params),
        te: (k) => hasKey(k),
        tm: (k) => getRaw(k),
        rt: (msg) => msg,
        d: () => undefined,
        n: () => undefined,
    };
}

const provideValue = makeProvideValue();

const stringsPlugin = {
    install(app) {
        if (app.config.globalProperties.__skyblockhubStringsInstalled) {
            return;
        }

        app.config.globalProperties.__skyblockhubStringsInstalled = true;
        app.config.globalProperties.$t = (k, params) => lookup(k, params);
        app.config.globalProperties.$te = (k) => hasKey(k);
        app.config.globalProperties.$tm = (k) => getRaw(k);
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

        app.provide(I18nInjectionKey, provideValue);
    },
    global: provideValue.global,
};

export default stringsPlugin;

import { createI18n } from 'vue-i18n';
import { markRaw } from 'vue';
import en from './en.json';
import cs from './cs.json';

const savedLocale = typeof localStorage !== 'undefined'
    ? localStorage.getItem('locale')
    : null;

const documentLocale = typeof document !== 'undefined'
    ? document.documentElement.lang
    : null;

const messages = {
    en: markRaw(en),
    cs: markRaw(cs),
};

const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: savedLocale || documentLocale || 'en',
    fallbackLocale: 'en',
    messages,
    missingWarn: false,
    fallbackWarn: false,
});

export default i18n;

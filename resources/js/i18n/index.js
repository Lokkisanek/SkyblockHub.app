import { createI18n } from 'vue-i18n';
import en from './en.json';
import cs from './cs.json';

const savedLocale = typeof localStorage !== 'undefined'
    ? localStorage.getItem('locale')
    : null;

const i18n = createI18n({
    legacy: false,
    locale: savedLocale || 'en',
    fallbackLocale: 'en',
    messages: { en, cs },
});

export default i18n;

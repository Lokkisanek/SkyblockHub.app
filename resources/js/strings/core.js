import { markRaw } from 'vue';
import en from './en.json';

export const messages = markRaw(en || {});

export const I18nInjectionKey = Symbol('SkyblockHubStrings');

export function lookup(path, params = {}) {
    if (!path) return '';
    const parts = path.split('.');
    let cur = messages;
    for (const p of parts) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
            cur = cur[p];
        } else {
            return path;
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

export function hasKey(path) {
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

export function getRaw(path) {
    if (!path) return undefined;
    const parts = path.split('.');
    let cur = messages;
    for (const p of parts) {
        if (cur && Object.prototype.hasOwnProperty.call(cur, p)) {
            cur = cur[p];
        } else {
            return undefined;
        }
    }
    return cur;
}

export function tokenizeTemplate(value) {
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

export const t = (k, params) => lookup(k, params);
export const te = (k) => hasKey(k);
export const tm = (k) => getRaw(k);

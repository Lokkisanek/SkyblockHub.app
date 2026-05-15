/**
 * Minecraft § color/format codes → HTML (uses --mc-* CSS variables from app.css).
 */

const CODE_RE = /^§([0-9a-fk-or])$/i;

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function makeSpan(text, color, formats) {
    let attrs = '';
    if (color != null) {
        attrs += ` style="color: var(--mc-${color})"`;
    }
    const fmtKeys = Object.keys(formats);
    if (fmtKeys.length) {
        attrs += ` class="${fmtKeys.map((c) => `mc-fmt-${c}`).join(' ')}"`;
    }
    return `<span${attrs}>${text}</span>`;
}

/**
 * @param {string} text
 * @returns {string}
 */
export function colorCodeToHtml(text) {
    if (text == null || text === '') {
        return '';
    }

    const parts = String(text).split(/(§[0-9a-fk-or])/i);
    if (parts.length === 1) {
        return escapeHtml(parts[0]);
    }

    let output = '';
    let color = null;
    /** @type {Record<string, boolean>} */
    const formats = {};

    for (const part of parts) {
        const m = part.match(CODE_RE);
        if (m) {
            const code = m[1].toLowerCase();
            if (/[0-9a-f]/.test(code)) {
                color = code;
                for (const k of Object.keys(formats)) {
                    delete formats[k];
                }
            } else if (/[k-o]/.test(code)) {
                formats[code] = true;
            } else if (code === 'r') {
                color = null;
                for (const k of Object.keys(formats)) {
                    delete formats[k];
                }
            }
            continue;
        }

        const safe = escapeHtml(part);
        if (safe !== '') {
            output += makeSpan(safe, color, formats);
        }
    }

    return output;
}

/** Skill average value color (Hypixel-style tier bands). */
export function skillAvgColorCode(level) {
    const n = Number(level);
    if (!Number.isFinite(n)) return '7';
    if (n >= 50) return '6';
    if (n >= 40) return 'e';
    if (n >= 25) return 'a';
    if (n >= 10) return 'f';
    return '7';
}

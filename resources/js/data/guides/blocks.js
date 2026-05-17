export function section(id, heading, level, blocks) {
    return { id, heading, level, blocks };
}

export function p(text) {
    return { type: 'paragraph', text };
}

export function table(headers, rows) {
    return { type: 'table', headers, rows };
}

export function callout(title, text, variant = 'info') {
    return { type: 'callout', title, text, variant };
}

export function list(items, ordered = false) {
    return { type: 'list', items, ordered };
}

export function links(items) {
    return { type: 'links', items };
}

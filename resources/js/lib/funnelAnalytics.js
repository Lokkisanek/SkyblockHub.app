const MAX_EVENT_NAME_LENGTH = 64;

function getCsrfToken() {
    const tokenTag = document.querySelector('meta[name="csrf-token"]');
    return tokenTag?.getAttribute('content') || '';
}

export async function trackFunnelEvent(eventName, properties = {}, context = {}) {
    if (typeof window === 'undefined') {
        return;
    }

    if (typeof eventName !== 'string' || eventName.length === 0 || eventName.length > MAX_EVENT_NAME_LENGTH) {
        return;
    }

    try {
        const enrichedProperties = {
            ...properties,
            ...getUtmParams(window.location.search, properties),
        };

        await fetch('/analytics/funnel-event', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                event_name: eventName,
                properties: enrichedProperties,
                context,
            }),
        });
    } catch (_) {
        // Analytics failures should never block user actions.
    }
}

function getUtmParams(search, existing = {}) {
    if (!search || typeof search !== 'string') {
        return {};
    }

    const params = new URLSearchParams(search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    const result = {};

    for (const key of keys) {
        if (existing[key] !== undefined && existing[key] !== null && String(existing[key]) !== '') {
            continue;
        }

        const value = params.get(key);
        if (value) {
            result[key] = value;
        }
    }

    return result;
}

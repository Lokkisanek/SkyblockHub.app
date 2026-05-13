import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const appConfig = window.__SKYBLOCKHUB_CONFIG__ || {};

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;
const schemeRaw = (import.meta.env.VITE_REVERB_SCHEME ?? 'https').toString().toLowerCase();
const reverbScheme = schemeRaw === 'http' ? 'http' : 'https';

const rawPort = import.meta.env.VITE_REVERB_PORT;
const portStr = rawPort != null && String(rawPort).trim() !== '' ? String(rawPort).trim() : '';
// Public port the browser uses (443 behind TLS), not REVERB_SERVER_PORT (often 8080).
const reverbPort = portStr === '' ? (reverbScheme === 'https' ? 443 : 80) : Number.parseInt(portStr, 10);

const canConnect =
    appConfig.broadcastingEnabled &&
    reverbKey &&
    reverbHost &&
    Number.isFinite(reverbPort) &&
    reverbPort > 0;

if (canConnect) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });

    const pusher = window.Echo.connector?.pusher;
    pusher?.connection?.bind('error', (payload) => {
        const body =
            (typeof payload?.error?.data === 'string' && payload.error.data) ||
            (typeof payload?.data === 'string' && payload.data) ||
            '';
        if (body.includes('<!DOCTYPE')) {
            console.warn(
                '[Echo/Reverb] WebSocket endpoint returned HTML (usually 404). '
                    + 'Proxy Pusher paths (/app, /apps) to Reverb; set REVERB_PORT and VITE_REVERB_PORT to the public TLS port (443), '
                    + 'not the internal Reverb listen port.',
            );
        }
    });
} else {
    // Broadcasting disabled, incomplete Vite env, or invalid port — avoid creating Echo.
}

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const appConfig = window.__SKYBLOCKHUB_CONFIG__ || {};

// Only instantiate Echo when the server says broadcasting is enabled and
// the production Vite env exposes the Reverb connection details.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;
const reverbPort = import.meta.env.VITE_REVERB_PORT;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

if (appConfig.broadcastingEnabled && reverbKey && reverbHost && import.meta.env.PROD) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort ?? 80,
        wssPort: reverbPort ?? 443,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    // If broadcasting is disabled or the env is incomplete, avoid creating Echo.
    // window.Echo remains undefined and code that listens should check for window.Echo.
}

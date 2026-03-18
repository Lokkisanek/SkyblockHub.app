self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    const data = event.data || {};

    if (data.type !== 'SHOW_NOTIFICATION') {
        return;
    }

    const payload = data.payload || {};
    const title = payload.title || 'SkyblockHub Event Reminder';
    const options = {
        body: payload.body || 'Your tracked event is starting soon.',
        tag: payload.tag || 'event-timer',
        renotify: false,
        data: payload.data || {},
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil((async () => {
        const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of allClients) {
            if ('focus' in client) {
                client.focus();
                client.postMessage({ type: 'EVENT_TIMER_FOCUS' });
                return;
            }
        }

        if (clients.openWindow) {
            await clients.openWindow('/event-timer');
        }
    })());
});

/* ZCStats — PWA + Web Push */
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {
        title: 'ZCStats',
        body: '',
        url: '/',
        tag: 'zcstats',
    };

    try {
        if (event.data && typeof event.data.json === 'function') {
            const parsed = event.data.json();
            if (parsed && typeof parsed === 'object') {
                payload = { ...payload, ...parsed };
            }
        }
    } catch {
        /* ignore invalid payload */
    }

    const url = typeof payload.url === 'string' && payload.url.length > 0 ? payload.url : '/';

    event.waitUntil(
        self.registration.showNotification(payload.title || 'ZCStats', {
            body: payload.body || '',
            icon: '/images/zcstatslogo.png',
            tag: payload.tag || 'zcstats',
            data: { url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const raw = event.notification.data && event.notification.data.url;
    let targetUrl = typeof raw === 'string' && raw.length > 0 ? raw : '/';

    try {
        targetUrl = new URL(targetUrl, self.location.origin).href;
    } catch {
        targetUrl = self.location.origin + '/';
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
            return undefined;
        })
    );
});

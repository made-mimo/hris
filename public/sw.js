// Spec Section A7: "a lightweight service worker (registered for push only —
// no offline caching or install manifest, since installability is out of
// scope per Section 3.5)". This file intentionally does nothing but listen
// for push events and notification clicks.

self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/favicon.ico',
            data: { deepLink: data.deepLink },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const deepLink = event.notification.data?.deepLink || '/';

    event.waitUntil(clients.openWindow(deepLink));
});

// Service Worker for Push Notifications
// Water Truck On-Demand Platform

const CACHE_NAME = 'water-truck-v1';

// Install event
self.addEventListener('install', (event) => {
    console.log('[SW] Service Worker installed');
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('[SW] Service Worker activated');
    event.waitUntil(clients.claim());
});

// Push event - handle incoming push notifications
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');
    
    let data = {
        title: 'Water Truck Alert',
        body: 'You have a new notification',
        icon: '/images/868Water_logo.png',
        badge: '/images/868Water_logo.png',
        data: { url: '/truck' }
    };
    
    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch (e) {
            console.error('[SW] Error parsing push data:', e);
        }
    }
    
    const options = {
        body: data.body,
        icon: data.icon || '/images/868Water_logo.png',
        badge: data.badge || '/images/868Water_logo.png',
        vibrate: [200, 100, 200],
        tag: 'water-truck-notification',
        renotify: true,
        requireInteraction: true,
        data: data.data || { url: '/truck' },
        actions: [
            {
                action: 'open',
                title: 'Go Online'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event - handle user clicking on notification
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked');
    
    event.notification.close();
    
    const action = event.action;
    const notificationData = event.notification.data || {};
    const url = notificationData.url || '/truck';
    
    if (action === 'dismiss') {
        return;
    }
    
    // Open or focus the app
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url.includes('/truck') && 'focus' in client) {
                        return client.focus();
                    }
                }
                // If no window open, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Handle notification close
self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification closed');
});

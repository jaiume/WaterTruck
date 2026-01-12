// Service Worker for Push Notifications
// Water Truck On-Demand Platform - Unified Notification System

const CACHE_NAME = 'water-truck-v2';

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
        data: { url: '/', type: 'general' }
    };
    
    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch (e) {
            console.error('[SW] Error parsing push data:', e);
        }
    }
    
    // Determine notification options based on type
    const notificationType = data.data?.type || 'general';
    let options = {
        body: data.body,
        icon: data.icon || '/images/868Water_logo.png',
        badge: data.badge || '/images/868Water_logo.png',
        vibrate: [200, 100, 200],
        renotify: true,
        requireInteraction: true,
        data: data.data || { url: '/' }
    };
    
    // Customize based on notification type
    switch (notificationType) {
        case 'water_collected':
            // Customer notification - water is on the way
            options.tag = 'water-delivery-' + (data.data?.job_id || 'notification');
            options.actions = [
                { action: 'track', title: 'Track Delivery' },
                { action: 'dismiss', title: 'Dismiss' }
            ];
            break;
            
        case 'customers_nearby':
            // Truck notification - customers looking for water
            options.tag = 'truck-customers-notification';
            options.actions = [
                { action: 'open', title: 'Go Online' },
                { action: 'dismiss', title: 'Dismiss' }
            ];
            break;
            
        default:
            // General notification
            options.tag = 'water-truck-notification';
            options.actions = [
                { action: 'open', title: 'Open' },
                { action: 'dismiss', title: 'Dismiss' }
            ];
    }
    
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
    const notificationType = notificationData.type || 'general';
    let url = notificationData.url || '/';
    
    // Handle dismiss action
    if (action === 'dismiss') {
        return;
    }
    
    // Determine URL based on notification type and action
    if (notificationType === 'water_collected') {
        // Customer notification - navigate to job tracking page
        url = notificationData.url || `/job/${notificationData.job_id}`;
    } else if (notificationType === 'customers_nearby') {
        // Truck notification - navigate to truck dashboard
        url = '/truck';
    }
    
    // Open or focus the app
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Try to focus an existing window matching the URL path
                const urlPath = new URL(url, self.location.origin).pathname;
                
                for (const client of clientList) {
                    const clientPath = new URL(client.url).pathname;
                    if (clientPath.startsWith(urlPath) || clientPath === urlPath) {
                        return client.focus();
                    }
                }
                
                // If no matching window, try to focus any window and navigate
                for (const client of clientList) {
                    if ('focus' in client && 'navigate' in client) {
                        return client.focus().then(() => client.navigate(url));
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

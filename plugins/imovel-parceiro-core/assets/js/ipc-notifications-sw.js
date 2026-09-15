self.addEventListener('push', function(event) {
    var data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = { title: 'Imóvel Parceiro', body: event.data.text() };
        }
    }

    var title = data.title || 'Imóvel Parceiro';
    var options = {
        body: data.body || data.message || '',
        icon: data.icon || '/wp-content/plugins/imovel-parceiro-core/assets/img/icon-192.png',
        badge: data.badge || data.icon || '/wp-content/plugins/imovel-parceiro-core/assets/img/icon-192.png',
        data: {
            url: data.url || '/'
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var targetUrl = (event.notification && event.notification.data && event.notification.data.url) ? event.notification.data.url : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
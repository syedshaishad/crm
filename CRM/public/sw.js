/* CRM web push service worker. */
self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  var data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { body: event.data ? event.data.text() : '' };
  }

  var title = data.title || 'New notification';
  var options = {
    body: data.body || '',
    icon: data.icon || undefined,
    badge: data.badge || undefined,
    image: data.image || undefined,
    tag: data.tag || undefined,
    renotify: !!data.tag,
    silent: false,
    vibrate: data.vibrate || [120, 60, 120],
    timestamp: data.timestamp || Date.now(),
    data: { url: data.url || self.registration.scope }
  };

  event.waitUntil(
    Promise.all([
      self.registration.showNotification(title, options),
      self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
        clients.forEach(function (client) {
          client.postMessage({
            type: 'crm-push',
            title: title,
            body: options.body,
            url: options.data.url
          });
        });
      })
    ])
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = new URL(event.notification.data && event.notification.data.url ? event.notification.data.url : self.registration.scope, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
      for (var i = 0; i < clients.length; i++) {
        var client = clients[i];
        if (client.url === target && 'focus' in client) return client.focus();
      }
      for (var j = 0; j < clients.length; j++) {
        var open = clients[j];
        if ('navigate' in open) return open.navigate(target).then(function (c) { return c && c.focus ? c.focus() : c; });
      }
      if (self.clients.openWindow) return self.clients.openWindow(target);
    })
  );
});

jQuery(function($){
    if (!(window.ipcNotifications && ipcNotifications.ajax_url && ipcNotifications.nonce)) {
        return;
    }

    var unreadCount = 0;
    var notificationCache = [];
    var refreshTimer = null;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function unreadLabel() {
        if (unreadCount > 0) {
            return ipcNotifications.strings.notifications + ' - existem notificações não lidas';
        }

        return ipcNotifications.strings.notifications;
    }

    function updateBadges() {
        $('.ipc-notifications-badge').toggle(unreadCount > 0).text(unreadCount > 99 ? '99+' : unreadCount);
        $('.ipc-notifications-toggle').toggleClass('has-unread', unreadCount > 0);
        $('.ipc-notifications-toggle').attr('aria-label', unreadLabel());
    }

    function renderDropdown(list) {
        var $menu = $('.ipc-notifications-dropdown');
        if (!$menu.length) {
            return;
        }

        if (!list || !list.length) {
            $menu.html('<li><span class="dropdown-item-text text-muted">Sem notificações recentes.</span></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item ipc-notifications-view-all" href="' + escapeHtml(ipcNotifications.notifications_url) + '">' + escapeHtml(ipcNotifications.strings.view_all) + '</a></li>');
            return;
        }

        var html = '';
        list.forEach(function(item){
            var unreadClass = item.is_read ? '' : ' ipc-notification-unread';
            var priorityClass = item.priority === 'critical' ? ' ipc-notification-critical' : '';
            html += '<li>' +
                '<a href="' + escapeHtml(item.url || ipcNotifications.notifications_url) + '" class="dropdown-item ipc-notification-item' + unreadClass + priorityClass + '" data-notification-id="' + escapeHtml(item.id) + '">' +
                    '<strong class="d-block">' + (item.is_read ? '' : '<span class="ipc-notification-dot"></span>') + escapeHtml(item.title) + '</strong>' +
                    '<small class="d-block text-muted">' + escapeHtml(item.message || '') + '</small>' +
                    '<small class="d-block text-muted">' + escapeHtml(item.created_at || '') + '</small>' +
                '</a>' +
            '</li>';
        });

        html += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item ipc-notifications-view-all" href="' + escapeHtml(ipcNotifications.notifications_url) + '">' + escapeHtml(ipcNotifications.strings.view_all) + '</a></li>';
        $menu.html(html);
    }

    function refreshNotifications() {
        return $.post(ipcNotifications.ajax_url, {
            action: 'imovel_parceiro_notifications_fetch',
            nonce: ipcNotifications.nonce,
            limit: 6
        }, function(response){
            if (response && response.success && response.data) {
                unreadCount = parseInt(response.data.unread_count, 10) || 0;
                notificationCache = response.data.notifications || [];
                updateBadges();
                renderDropdown(notificationCache);
            }
        });
    }

    function openMenu($toggle) {
        var $wrapper = $toggle.closest('.ipc-notifications-wrap');
        $wrapper.addClass('show');
        $toggle.attr('aria-expanded', 'true');
        refreshNotifications();
    }

    function closeMenu($toggle) {
        var $wrapper = $toggle.closest('.ipc-notifications-wrap');
        $wrapper.removeClass('show');
        $toggle.attr('aria-expanded', 'false');
    }

    function markRead(notificationId, callback) {
        return $.post(ipcNotifications.ajax_url, {
            action: 'imovel_parceiro_notifications_mark_read',
            nonce: ipcNotifications.nonce,
            notification_id: notificationId
        }, function(response){
            if (response && response.success && response.data) {
                unreadCount = parseInt(response.data.unread_count, 10) || 0;
                updateBadges();
            }

            if (typeof callback === 'function') {
                callback(response);
            }
        });
    }

    function markAllRead() {
        return $.post(ipcNotifications.ajax_url, {
            action: 'imovel_parceiro_notifications_mark_all_read',
            nonce: ipcNotifications.nonce
        }, function(response){
            if (response && response.success && response.data) {
                unreadCount = 0;
                updateBadges();
                refreshNotifications();
            }
        });
    }

    function ensureServiceWorker() {
        if (!('serviceWorker' in navigator) || !ipcNotifications.service_worker_url) {
            return Promise.resolve(null);
        }

        return navigator.serviceWorker.register(ipcNotifications.service_worker_url).catch(function(){
            return null;
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function saveSubscription(subscription) {
        if (!subscription) {
            return $.Deferred().resolve().promise();
        }

        return $.post(ipcNotifications.ajax_url, {
            action: 'imovel_parceiro_notifications_save_subscription',
            nonce: ipcNotifications.nonce,
            subscription: JSON.stringify(subscription),
            device_label: navigator.userAgent || ''
        });
    }

    function enableBrowserPush() {
        if (!('Notification' in window) || !('PushManager' in window)) {
            return;
        }

        Notification.requestPermission().then(function(permission){
            if (permission !== 'granted') {
                return;
            }

            ensureServiceWorker().then(function(registration){
                if (!registration || !ipcNotifications.vapid_public_key) {
                    return;
                }

                registration.pushManager.getSubscription().then(function(existing){
                    if (existing) {
                        return saveSubscription(existing);
                    }

                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(ipcNotifications.vapid_public_key)
                    }).then(saveSubscription);
                });
            });
        });
    }

    $(document).on('click', '.ipc-notifications-toggle', function(e){
        e.preventDefault();
        var $toggle = $(this);
        var isOpen = $toggle.closest('.ipc-notifications-wrap').hasClass('show');

        if (isOpen) {
            closeMenu($toggle);
            return;
        }

        openMenu($toggle);
    });

    $(document).on('click', '.ipc-notification-item', function(e){
        e.preventDefault();

        var $item = $(this);
        var notificationId = parseInt($item.data('notification-id'), 10) || 0;
        var targetUrl = $item.attr('href') || ipcNotifications.notifications_url;

        if (!notificationId) {
            window.location.href = targetUrl;
            return;
        }

        markRead(notificationId, function(){
            window.location.href = targetUrl;
        });
    });

    $(document).on('click', '.ipc-notifications-mark-all', function(e){
        e.preventDefault();
        markAllRead();
    });

    $(document).on('click', '.ipc-notifications-enable-push', function(e){
        e.preventDefault();
        enableBrowserPush();
    });

    $(document).on('click', function(e){
        if ($(e.target).closest('.ipc-notifications-wrap').length === 0) {
            $('.ipc-notifications-wrap').removeClass('show');
            $('.ipc-notifications-toggle').attr('aria-expanded', 'false');
        }
    });

    refreshNotifications();
    refreshTimer = window.setInterval(refreshNotifications, 60000);

    $(window).on('beforeunload', function(){
        if (refreshTimer) {
            window.clearInterval(refreshTimer);
        }
    });
});